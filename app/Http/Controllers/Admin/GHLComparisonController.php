<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGHLComparisonJob;
use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use App\Services\GHLComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GHLComparisonController extends Controller
{
    protected $baremetricsService;
    protected $comparisonService;

    public function __construct(BaremetricsService $baremetricsService, GHLComparisonService $comparisonService)
    {
        $this->baremetricsService = $baremetricsService;
        $this->comparisonService = $comparisonService;
    }

    /**
     * Mostrar lista de comparaciones
     */
    public function index()
    {
        $comparisons = ComparisonRecord::withCount([
            'missingUsers',
            'missingUsers as pending_count' => function($query) {
                $query->where('import_status', 'pending');
            },
            'missingUsers as imported_count' => function($query) {
                $query->where('import_status', 'imported');
            },
            'missingUsers as failed_count' => function($query) {
                $query->where('import_status', 'failed');
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        return view('admin.ghl-comparison.index', compact('comparisons'));
    }

    /**
     * Mostrar formulario para crear nueva comparación
     */
    public function create()
    {
        return view('admin.ghl-comparison.create');
    }

    /**
     * Procesar nueva comparación
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        try {
            DB::beginTransaction();

            // Guardar archivo CSV
            $file = $request->file('csv_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('csv/comparisons', $fileName, 'public');

            // Crear registro de comparación
            $comparison = ComparisonRecord::create([
                'name' => $request->name,
                'csv_file_path' => $filePath,
                'csv_file_name' => $file->getClientOriginalName(),
                'status' => 'pending',
            ]);

            DB::commit();

            // Log para debug
            Log::info("Comparación creada exitosamente", [
                'comparison_id' => $comparison->id,
                'name' => $comparison->name,
                'file_path' => $filePath
            ]);

            // Redirigir a vista de procesamiento
            return redirect()
                ->route('admin.ghl-comparison.processing', $comparison)
                ->with('success', 'Comparación creada exitosamente. Redirigiendo a vista de progreso...');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating comparison', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error al procesar la comparación: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar detalles de una comparación
     */
    public function show(ComparisonRecord $comparison)
    {
        $comparison->load([
            'missingUsers' => function($query) {
                $query->orderBy('created_at', 'desc');
            }
        ]);

        $stats = $comparison->import_stats;

        return view('admin.ghl-comparison.show', compact('comparison', 'stats'));
    }

    /**
     * Mostrar usuarios faltantes de una comparación
     */
    public function missingUsers(ComparisonRecord $comparison, Request $request)
    {
        $query = $comparison->missingUsers();

        // Filtros
        if ($request->filled('status')) {
            $query->where('import_status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('email', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%')
                  ->orWhere('company', 'like', '%' . $request->search . '%');
            });
        }

        $missingUsers = $query->orderBy('created_at', 'desc')->paginate(50);

        return view('admin.ghl-comparison.missing-users', compact('comparison', 'missingUsers'));
    }

    /**
     * Importar usuarios faltantes a Baremetrics
     */
    public function importUsers(ComparisonRecord $comparison, Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:missing_users,id',
        ]);

        try {
            $userIds = $request->user_ids;
            $users = $comparison->missingUsers()->whereIn('id', $userIds)->get();

            $imported = 0;
            $failed = 0;

            foreach ($users as $user) {
                try {
                    $user->markAsImporting();
                    
                    // Importar usuario a Baremetrics
                    $customerId = $this->baremetricsService->createCustomerSimple([
                        'email' => $user->email,
                        'name' => $user->name,
                        'company' => $user->company,
                        'phone' => $user->phone,
                    ]);

                    $user->markAsImported($customerId);
                    $imported++;

                } catch (\Exception $e) {
                    $user->markAsFailed($e->getMessage());
                    $failed++;
                    
                    Log::error('Error importing user to Baremetrics', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Importación completada: {$imported} usuarios importados";
            if ($failed > 0) {
                $message .= ", {$failed} usuarios fallaron";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error in bulk import', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error en la importación masiva: ' . $e->getMessage());
        }
    }

    /**
     * Importar todos los usuarios faltantes
     */
    public function importAllUsers(ComparisonRecord $comparison)
    {
        try {
            $pendingUsers = $comparison->pendingMissingUsers()->get();
            
            if ($pendingUsers->isEmpty()) {
                return back()->with('info', 'No hay usuarios pendientes de importar.');
            }

            $imported = 0;
            $failed = 0;

            foreach ($pendingUsers as $user) {
                try {
                    $user->markAsImporting();
                    
                    $customerId = $this->baremetricsService->createCustomerSimple([
                        'email' => $user->email,
                        'name' => $user->name,
                        'company' => $user->company,
                        'phone' => $user->phone,
                    ]);

                    $user->markAsImported($customerId);
                    $imported++;

                } catch (\Exception $e) {
                    $user->markAsFailed($e->getMessage());
                    $failed++;
                    
                    Log::error('Error importing user to Baremetrics', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Importación masiva completada: {$imported} usuarios importados";
            if ($failed > 0) {
                $message .= ", {$failed} usuarios fallaron";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error in bulk import all', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error en la importación masiva: ' . $e->getMessage());
        }
    }

    /**
     * Reintentar importación de usuario fallido
     */
    public function retryImport(MissingUser $user)
    {
        try {
            $user->markAsImporting();
            
            $customerId = $this->baremetricsService->createCustomerSimple([
                'email' => $user->email,
                'name' => $user->name,
                'company' => $user->company,
                'phone' => $user->phone,
            ]);

            $user->markAsImported($customerId);

            return back()->with('success', 'Usuario importado exitosamente.');

        } catch (\Exception $e) {
            $user->markAsFailed($e->getMessage());
            
            Log::error('Error retrying import', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al importar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Importar usuario individual con plan y suscripción
     */
    public function importUserWithPlan(MissingUser $user)
    {
        try {
            // Forzar entorno sandbox para importaciones individuales
            config(['services.baremetrics.environment' => 'sandbox']);
            
            // Reinstanciar el servicio con la nueva configuración
            $this->baremetricsService = new BaremetricsService();
            
            $user->markAsImporting();
            
            // Determinar el plan basado en los tags del usuario
            $planData = $this->determinePlanFromTags($user->tags);
            
            // Crear datos del cliente
            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'notes' => "Importado desde GHL - Tags: {$user->tags}",
                'oid' => 'cust_' . uniqid(),
            ];

            // Crear datos de suscripción
            $subscriptionData = [
                'oid' => 'sub_' . uniqid(),
                'started_at' => now()->timestamp, // Usar timestamp Unix
                'status' => 'active',
                'canceled_at' => null,
                'canceled_reason' => null,
            ];

            // Crear configuración completa del cliente en Baremetrics
            $result = $this->baremetricsService->createCompleteCustomerSetup(
                $customerData,
                $planData,
                $subscriptionData
            );

            if ($result && isset($result['customer']['customer']['oid'])) {
                $customerOid = $result['customer']['customer']['oid'];
                $user->markAsImported($customerOid);

                Log::info('Usuario importado exitosamente con plan y suscripción', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'plan_name' => $planData['name'],
                    'customer_oid' => $customerOid,
                    'result' => $result
                ]);

                return back()->with('success', "Usuario {$user->email} importado exitosamente con plan '{$planData['name']}'.");
            } else {
                throw new \Exception('No se pudo crear la configuración completa del cliente');
            }

        } catch (\Exception $e) {
            $user->markAsFailed($e->getMessage());
            
            Log::error('Error importing user with plan', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tags' => $user->tags,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', "Error al importar usuario {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Determinar el plan basado en los tags del usuario
     */
    private function determinePlanFromTags(?string $tags): array
    {
        if (empty($tags)) {
            // Plan por defecto si no hay tags
            return [
                'name' => 'creetelo_mensual',
                'interval' => 'month',
                'interval_count' => 1,
                'amount' => 0, // Precio por defecto
                'currency' => 'usd',
                'oid' => 'plan_' . uniqid(),
            ];
        }

        $tagsArray = array_map('trim', explode(',', $tags));
        
        // Buscar tags específicos de suscripción
        foreach ($tagsArray as $tag) {
            $tag = strtolower($tag);
            
            if (strpos($tag, 'creetelo_anual') !== false || strpos($tag, 'créetelo_anual') !== false) {
                return [
                    'name' => 'creetelo_anual',
                    'interval' => 'year',
                    'interval_count' => 1,
                    'amount' => 0, // Precio por defecto
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
            
            if (strpos($tag, 'creetelo_mensual') !== false || strpos($tag, 'créetelo_mensual') !== false) {
                return [
                    'name' => 'creetelo_mensual',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => 0, // Precio por defecto
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
        }

        // Si no encuentra tags específicos, usar el primer tag como nombre del plan
        $firstTag = trim($tagsArray[0]);
        $interval = 'month'; // Por defecto mensual
        
        if (strpos($firstTag, 'anual') !== false || strpos($firstTag, 'year') !== false) {
            $interval = 'year';
        }

        return [
            'name' => $firstTag,
            'interval' => $interval,
            'interval_count' => 1,
            'amount' => 0, // Precio por defecto
            'currency' => 'usd',
            'oid' => 'plan_' . uniqid(),
        ];
    }

    /**
     * Eliminar comparación
     */
    public function destroy(ComparisonRecord $comparison)
    {
        try {
            // Eliminar archivo CSV
            if (Storage::disk('public')->exists($comparison->csv_file_path)) {
                Storage::disk('public')->delete($comparison->csv_file_path);
            }

            // Eliminar comparación (cascade eliminará usuarios faltantes)
            $comparison->delete();

            return redirect()
                ->route('admin.ghl-comparison.index')
                ->with('success', 'Comparación eliminada exitosamente.');

        } catch (\Exception $e) {
            Log::error('Error deleting comparison', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al eliminar la comparación: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar vista de procesamiento
     */
    public function processing(ComparisonRecord $comparison)
    {
        return view('admin.ghl-comparison.processing-vanilla', compact('comparison'));
    }

    /**
     * Iniciar procesamiento de comparación (AJAX)
     */
    public function startProcessing(ComparisonRecord $comparison)
    {
        try {
            if ($comparison->status === 'pending') {
                // Procesar comparación en background
                $this->comparisonService->processComparison($comparison);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Procesamiento iniciado exitosamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'La comparación ya está siendo procesada o completada'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error starting comparison processing', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error iniciando procesamiento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener progreso de comparación (AJAX)
     */
    public function getProgress(ComparisonRecord $comparison)
    {
        return response()->json($comparison->getProgressInfo());
    }

    /**
     * Descargar CSV de usuarios faltantes
     */
    public function downloadMissingUsers(ComparisonRecord $comparison)
    {
        $missingUsers = $comparison->missingUsers()->get();
        
        $filename = 'missing_users_' . $comparison->id . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($missingUsers) {
            $file = fopen('php://output', 'w');
            
            // Headers del CSV
            fputcsv($file, [
                'Email',
                'Name',
                'Company',
                'Phone',
                'Tags',
                'Created Date',
                'Last Activity',
                'Import Status',
                'Baremetrics Customer ID',
                'Imported At'
            ]);

            // Datos
            foreach ($missingUsers as $user) {
                fputcsv($file, [
                    $user->email,
                    $user->name,
                    $user->company,
                    $user->phone,
                    $user->tags,
                    $user->created_date?->format('Y-m-d'),
                    $user->last_activity?->format('Y-m-d'),
                    $user->import_status,
                    $user->baremetrics_customer_id,
                    $user->imported_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}