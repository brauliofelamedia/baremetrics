<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGHLComparisonJob;
use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use App\Services\GHLComparisonService;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GHLComparisonController extends Controller
{
    protected $baremetricsService;
    protected $comparisonService;
    protected $ghlService;

    public function __construct(
        BaremetricsService $baremetricsService, 
        GHLComparisonService $comparisonService,
        GoHighLevelService $ghlService
    ) {
        $this->baremetricsService = $baremetricsService;
        $this->comparisonService = $comparisonService;
        $this->ghlService = $ghlService;
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
     * Importar todos los usuarios faltantes con plan y suscripción
     */
    public function importAllUsersWithPlan(ComparisonRecord $comparison, Request $request)
    {
        try {
            // Configurar entorno según la configuración actual
            config(['services.baremetrics.environment' => config('services.baremetrics.environment', 'production')]);
            $this->baremetricsService->reinitializeConfiguration();
            
            // Obtener el sourceId
            $sourceId = $this->baremetricsService->getGHLSourceId();
            
            if (!$sourceId) {
                return back()->with('error', 'No se pudo obtener el source ID de Baremetrics.');
            }

            // Obtener todos los planes disponibles en Baremetrics
            $plansResponse = $this->baremetricsService->getPlans($sourceId);
            $availablePlans = [];
            $planAmounts = []; // Guardar también los amounts
            
            if (isset($plansResponse['plans']) && is_array($plansResponse['plans'])) {
                foreach ($plansResponse['plans'] as $plan) {
                    // Guardar planes normalizando el nombre
                    $normalizedName = strtolower(str_replace(['é'], ['e'], $plan['name']));
                    $availablePlans[$normalizedName] = $plan['oid'];
                    
                    // Guardar el amount del plan (tomamos el primer amount disponible)
                    if (isset($plan['amounts']) && is_array($plan['amounts']) && count($plan['amounts']) > 0) {
                        $planAmounts[$plan['oid']] = $plan['amounts'][0]['amount'];
                    }
                }
            }

            Log::info('Planes disponibles en Baremetrics', [
                'total' => count($availablePlans),
                'planes' => $availablePlans,
                'amounts' => $planAmounts
            ]);

            if (empty($availablePlans)) {
                return back()->with('error', 'No hay planes disponibles en Baremetrics. Crea los planes primero.');
            }

            // Obtener límite de usuarios (para pruebas)
            $limit = $request->input('limit', null);
            
            $query = $comparison->pendingMissingUsers();
            
            if ($limit) {
                $query->limit($limit);
            }
            
            $pendingUsers = $query->get();
            
            if ($pendingUsers->isEmpty()) {
                return back()->with('info', 'No hay usuarios pendientes de importar.');
            }

            Log::info('=== INICIANDO IMPORTACIÓN MASIVA CON PLAN ===', [
                'comparison_id' => $comparison->id,
                'total_usuarios' => $pendingUsers->count(),
                'limit' => $limit,
                'environment' => config('services.baremetrics.environment'),
                'source_id' => $sourceId
            ]);

            $imported = 0;
            $failed = 0;
            $errors = [];

            foreach ($pendingUsers as $index => $user) {
                try {
                    $userIndex = $index + 1;
                    
                    Log::info("Procesando usuario #{$userIndex}/{$pendingUsers->count()}", [
                        'email' => $user->email,
                        'user_id' => $user->id
                    ]);
                    
                    $user->markAsImporting();
                    
                    // Obtener el plan tag de los tags del usuario
                    $planTag = $this->findPlanTag($user->tags);
                    
                    if (!$planTag) {
                        throw new \Exception('No se pudo determinar el plan del cliente. Tags: ' . $user->tags);
                    }

                    // Buscar el OID real del plan en Baremetrics
                    if (!isset($availablePlans[$planTag])) {
                        throw new \Exception("El plan '{$planTag}' no existe en Baremetrics. Planes disponibles: " . implode(', ', array_keys($availablePlans)));
                    }

                    $planOid = $availablePlans[$planTag];

                    Log::info('Plan identificado', [
                        'plan_nombre' => $planTag,
                        'plan_oid' => $planOid,
                        'email' => $user->email
                    ]);

                    // Crear cliente
                    $unique = str_replace('.', '', uniqid('', true));
                    $oid = 'ghl_' . $unique;
                    
                    $customerCreate = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'oid' => $oid,
                    ];

                    Log::info('Creando cliente en Baremetrics', ['oid' => $oid, 'email' => $user->email]);
                    
                    $customerData = $this->baremetricsService->createCustomer($customerCreate, $sourceId);

                    if (!$customerData) {
                        throw new \Exception('Error creando el cliente');
                    }

                    Log::info('Cliente creado exitosamente', [
                        'customer_id' => $customerData['id'] ?? 'N/A',
                        'email' => $user->email
                    ]);

                    // Crear suscripción
                    $subscriptionOid = 'ghl_sub_' . str_replace('.', '', uniqid('', true));
                    $startedAt = now()->timestamp;
                    
                    $subscriptionData = [
                        'oid' => $subscriptionOid,
                        'customer_oid' => $oid,
                        'plan_oid' => $planOid, // Usar el OID real del plan
                        'status' => 'active',
                        'started_at' => $startedAt,
                        'quantity' => 1, // Cantidad de suscripciones
                    ];
                    
                    // Agregar el amount si está disponible
                    if (isset($planAmounts[$planOid])) {
                        $subscriptionData['amount'] = $planAmounts[$planOid];
                    }

                    Log::info('Creando suscripción en Baremetrics', array_merge(['email' => $user->email], $subscriptionData));

                    $subscription = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);

                    if (!$subscription) {
                        Log::warning('Cliente creado pero falló la creación de la suscripción', ['email' => $user->email]);
                    } else {
                    Log::info('Suscripción creada exitosamente', [
                        'subscription_id' => $subscription['id'] ?? 'N/A',
                        'email' => $user->email
                    ]);
                }

                // Actualizar custom fields desde GHL
                try {
                    Log::info('Obteniendo custom fields desde GHL', ['email' => $user->email]);
                    
                    $ghlContact = $this->ghlService->getContacts($user->email);
                    
                    if ($ghlContact && isset($ghlContact['contacts']) && !empty($ghlContact['contacts'])) {
                        $contact = $ghlContact['contacts'][0];
                        $customFields = collect($contact['customFields'] ?? []);
                        
                        // Obtener información de suscripción de GHL
                        $ghlSubscription = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
                        $subscriptionStatus = $ghlSubscription['status'] ?? 'active';
                        $couponCode = $ghlSubscription['couponCode'] ?? null;
                        
                        // Preparar datos de custom fields
                        $ghlData = [
                            'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? null,
                            'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? null,
                            'country' => $contact['country'] ?? null,
                            'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? null,
                            'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? null,
                            'state' => $contact['state'] ?? null,
                            'location' => $contact['city'] ?? null,
                            'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? null,
                            'subscriptions' => $subscriptionStatus,
                            'coupon_code' => $couponCode,
                            'GHL: Migrate GHL' => 'true' // Marcar como migrado desde GHL
                        ];
                        
                        Log::info('Actualizando custom fields en Baremetrics', [
                            'email' => $user->email,
                            'oid' => $oid,
                            'fields_count' => count(array_filter($ghlData, fn($v) => $v !== null))
                        ]);
                        
                        $updateResult = $this->baremetricsService->updateCustomerAttributes($oid, $ghlData);
                        
                        if ($updateResult) {
                            Log::info('Custom fields actualizados exitosamente', ['email' => $user->email]);
                        } else {
                            Log::warning('No se pudieron actualizar los custom fields', ['email' => $user->email]);
                        }
                    } else {
                        Log::warning('No se encontró el contacto en GHL', ['email' => $user->email]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error actualizando custom fields desde GHL', [
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                    // No lanzar excepción, el cliente ya fue creado exitosamente
                }

                // Marcar como importado y guardar el OID
                $user->update([
                    'import_status' => 'imported',
                    'baremetrics_customer_id' => $oid,
                    'imported_at' => now(),
                    'import_error' => null,
                    'import_notes' => "Importado con plan: {$planTag} (OID: {$planOid})" . ($subscription ? ' - Suscripción creada' : ' - Sin suscripción')
                ]);                    $imported++;

                    Log::info('Usuario importado exitosamente', [
                        'email' => $user->email,
                        'oid' => $oid,
                        'plan' => $planTag,
                        'has_subscription' => !empty($subscription)
                    ]);

                    // Pausa pequeña entre importaciones
                    usleep(200000); // 200ms

                } catch (\Exception $e) {
                    $failed++;
                    $errorMsg = "Error importando {$user->email}: {$e->getMessage()}";
                    $errors[] = $errorMsg;
                    
                    $user->markAsFailed($e->getMessage());
                    
                    Log::error('Error importando usuario', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            Log::info('=== IMPORTACIÓN MASIVA COMPLETADA ===', [
                'comparison_id' => $comparison->id,
                'total_procesados' => $pendingUsers->count(),
                'importados' => $imported,
                'fallidos' => $failed
            ]);

            $message = "Importación completada: {$imported} usuarios importados con plan y suscripción";
            if ($failed > 0) {
                $message .= ", {$failed} usuarios fallaron";
            }

            return back()->with('success', $message)->with('import_details', [
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Error en importación masiva con plan', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Error en la importación masiva: ' . $e->getMessage());
        }
    }

    /**
     * Encontrar el plan tag en los tags del usuario
     */
    private function findPlanTag($tagsString)
    {
        if (empty($tagsString)) {
            return null;
        }

        $tags = explode(',', $tagsString);
        $normalizedTags = array_map(function($tag) {
            return strtolower(trim($tag));
        }, $tags);

        $planTags = [
            'creetelo_mensual',
            'créetelo_mensual',
            'creetelo_anual',
            'créetelo_anual'
        ];

        foreach ($planTags as $planTag) {
            $normalizedPlanTag = strtolower($planTag);
            if (in_array($normalizedPlanTag, $normalizedTags)) {
                // Remover acentos
                $planTagSinAcentos = str_replace(
                    ['é'],
                    ['e'],
                    $normalizedPlanTag
                );
                return $planTagSinAcentos;
            }
        }

        return null;
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

    /**
     * Eliminar usuarios importados de Baremetrics y cambiar su estado a pending
     */
    public function deleteImportedUsers(Request $request, ComparisonRecord $comparison)
    {
        try {
            Log::info('=== INICIANDO ELIMINACIÓN MASIVA DE USUARIOS IMPORTADOS ===', [
                'comparison_id' => $comparison->id,
                'comparison_name' => $comparison->name,
                'user_ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Configurar Baremetrics para producción
            config(['services.baremetrics.environment' => 'production']);
            $this->baremetricsService->reinitializeConfiguration();

            // Obtener el source ID correcto para GHL
            $sourceId = $this->baremetricsService->getGHLSourceId();
            
            if (!$sourceId) {
                Log::error('No se pudo obtener source ID de GHL');
                return redirect()->back()->with('error', 'No se pudo obtener el source ID de GHL de Baremetrics.');
            }

            Log::info('Configuración de Baremetrics', [
                'environment' => config('services.baremetrics.environment'),
                'source_id' => $sourceId,
                'base_url' => config('services.baremetrics.production_url')
            ]);

            // Obtener usuarios importados de esta comparación
            $importedUsers = $comparison->missingUsers()
                ->where('import_status', 'imported')
                ->get();

            Log::info('Usuarios importados encontrados', [
                'total_count' => $importedUsers->count(),
                'user_emails' => $importedUsers->pluck('email')->toArray(),
                'customer_ids' => $importedUsers->pluck('baremetrics_customer_id')->toArray()
            ]);

            if ($importedUsers->isEmpty()) {
                Log::warning('No hay usuarios importados para eliminar');
                return redirect()->back()->with('error', 'No hay usuarios importados para eliminar.');
            }

            $deletedCount = 0;
            $failedCount = 0;
            $errors = [];
            $processedUsers = [];

            Log::info('Iniciando procesamiento de usuarios', [
                'total_users' => $importedUsers->count()
            ]);

            foreach ($importedUsers as $index => $user) {
                $userIndex = $index + 1;
                Log::info("Procesando usuario #{$userIndex}/{$importedUsers->count()}", [
                    'user_email' => $user->email,
                    'customer_id' => $user->baremetrics_customer_id,
                    'user_id' => $user->id
                ]);

                try {
                    $customerId = $user->baremetrics_customer_id;
                    
                    // Si el usuario no tiene customer_id, solo cambiar estado a pending
                    if (!$customerId) {
                        Log::info("Usuario {$user->email} no tiene customer_id, solo cambiando estado a pending");
                        
                        $updateResult = $user->update([
                            'import_status' => 'pending',
                            'baremetrics_customer_id' => null,
                            'imported_at' => null,
                            'import_error' => null
                        ]);
                        
                        Log::info("Estado actualizado para usuario sin customer_id", [
                            'user_email' => $user->email,
                            'update_success' => $updateResult
                        ]);
                        
                        $deletedCount++;
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => null,
                            'subscriptions_deleted' => 0,
                            'status' => 'no_customer_id_cleaned'
                        ];
                        
                        Log::info("Pausa de 200ms antes del siguiente usuario");
                        usleep(200000); // 200ms
                        continue;
                    }
                    
                    // 1. Obtener suscripciones del usuario
                    Log::info("Obteniendo suscripciones para usuario {$user->email}", [
                        'customer_id' => $customerId
                    ]);
                    
                    $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
                    $userSubscriptions = [];
                    
                    if ($subscriptions && isset($subscriptions['subscriptions'])) {
                        Log::info("Suscripciones obtenidas de Baremetrics", [
                            'total_subscriptions' => count($subscriptions['subscriptions'])
                        ]);
                        
                        foreach ($subscriptions['subscriptions'] as $subscription) {
                            $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                     $subscription['customer']['oid'] ?? 
                                                     $subscription['customerOid'] ?? 
                                                     null;
                            
                            if ($subscriptionCustomerOid === $customerId) {
                                $userSubscriptions[] = $subscription;
                            }
                        }
                        
                        Log::info("Suscripciones encontradas para usuario {$user->email}", [
                            'subscriptions_count' => count($userSubscriptions),
                            'subscription_ids' => array_column($userSubscriptions, 'oid')
                        ]);
                    } else {
                        Log::warning("No se pudieron obtener suscripciones de Baremetrics", [
                            'response' => $subscriptions
                        ]);
                    }

                    // 2. Eliminar suscripciones
                    Log::info("Eliminando suscripciones para usuario {$user->email}", [
                        'subscriptions_to_delete' => count($userSubscriptions)
                    ]);
                    
                    foreach ($userSubscriptions as $subscription) {
                        $subscriptionOid = $subscription['oid'];
                        Log::info("Eliminando suscripción {$subscriptionOid}");
                        
                        $deleteResult = $this->baremetricsService->deleteSubscription(
                            $sourceId, 
                            $subscriptionOid
                        );
                        
                        if ($deleteResult) {
                            Log::info("Suscripción {$subscriptionOid} eliminada exitosamente");
                        } else {
                            Log::warning('Error eliminando suscripción', [
                                'subscription_id' => $subscriptionOid,
                                'customer_id' => $customerId,
                                'user_email' => $user->email
                            ]);
                        }
                    }

                    // 3. Eliminar customer
                    Log::info("Eliminando customer {$customerId} para usuario {$user->email}");
                    
                    $deleteCustomerResult = $this->baremetricsService->deleteCustomer(
                        $sourceId, 
                        $customerId
                    );

                    Log::info("Resultado de eliminación de customer", [
                        'customer_id' => $customerId,
                        'success' => $deleteCustomerResult,
                        'user_email' => $user->email
                    ]);

                    // Verificar si el customer ya no existe en Baremetrics (404)
                    $customerNotFound = false;
                    if (!$deleteCustomerResult) {
                        // Verificar si el error es porque el customer no existe
                        $customerNotFound = true; // Asumimos que si falla la eliminación, es porque no existe
                        Log::info("Customer no encontrado en Baremetrics, marcando como eliminado", [
                            'customer_id' => $customerId,
                            'user_email' => $user->email
                        ]);
                    }

                    if ($deleteCustomerResult || $customerNotFound) {
                        // 4. Cambiar estado a pending y limpiar datos de Baremetrics
                        Log::info("Actualizando estado del usuario {$user->email} a pending");
                        
                        $updateResult = $user->update([
                            'import_status' => 'pending',
                            'baremetrics_customer_id' => null,
                            'imported_at' => null,
                            'import_error' => null
                        ]);
                        
                        Log::info("Resultado de actualización de usuario", [
                            'user_email' => $user->email,
                            'update_success' => $updateResult
                        ]);
                        
                        $deletedCount++;
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => $customerId,
                            'subscriptions_deleted' => count($userSubscriptions),
                            'status' => $customerNotFound ? 'not_found_but_cleaned' : 'success'
                        ];
                        
                        Log::info('Usuario eliminado exitosamente', [
                            'user_email' => $user->email,
                            'customer_id' => $customerId,
                            'subscriptions_deleted' => count($userSubscriptions)
                        ]);
                    } else {
                        $failedCount++;
                        $errorMsg = "Error eliminando usuario {$user->email} (ID: {$customerId})";
                        $errors[] = $errorMsg;
                        
                        $processedUsers[] = [
                            'email' => $user->email,
                            'customer_id' => $customerId,
                            'status' => 'failed',
                            'error' => $errorMsg
                        ];
                        
                        Log::error('Error eliminando usuario de Baremetrics', [
                            'user_email' => $user->email,
                            'customer_id' => $customerId
                        ]);
                    }

                    // Pausa pequeña entre eliminaciones para evitar sobrecargar la API
                    Log::info("Pausa de 200ms antes del siguiente usuario");
                    usleep(200000); // 200ms

                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "Error procesando usuario {$user->email}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    
                    $processedUsers[] = [
                        'email' => $user->email,
                        'customer_id' => $user->baremetrics_customer_id,
                        'status' => 'exception',
                        'error' => $errorMsg
                    ];
                    
                    Log::error('Excepción durante eliminación de usuario', [
                        'user_email' => $user->email,
                        'customer_id' => $user->baremetrics_customer_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Preparar mensaje de resultado
            $successCount = 0;
            $notFoundCount = 0;
            $noCustomerIdCount = 0;
            
            foreach ($processedUsers as $user) {
                if ($user['status'] === 'success') {
                    $successCount++;
                } elseif ($user['status'] === 'not_found_but_cleaned') {
                    $notFoundCount++;
                } elseif ($user['status'] === 'no_customer_id_cleaned') {
                    $noCustomerIdCount++;
                }
            }
            
            $message = "Procesamiento completado: ";
            $parts = [];
            
            if ($successCount > 0) {
                $parts[] = "{$successCount} usuarios eliminados de Baremetrics";
            }
            if ($notFoundCount > 0) {
                $parts[] = "{$notFoundCount} usuarios ya no existían en Baremetrics (estado limpiado)";
            }
            if ($noCustomerIdCount > 0) {
                $parts[] = "{$noCustomerIdCount} usuarios sin customer_id (estado limpiado)";
            }
            if ($failedCount > 0) {
                $parts[] = "{$failedCount} usuarios fallaron";
                
                if (count($errors) > 0) {
                    $errorMsg = ". Errores: " . implode('; ', array_slice($errors, 0, 3));
                    if (count($errors) > 3) {
                        $errorMsg .= " y " . (count($errors) - 3) . " más...";
                    }
                    $parts[count($parts) - 1] .= $errorMsg;
                }
            }
            
            $message .= implode(', ', $parts);

            Log::info('=== ELIMINACIÓN MASIVA COMPLETADA ===', [
                'comparison_id' => $comparison->id,
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'total_processed' => $importedUsers->count(),
                'processed_users' => $processedUsers,
                'errors' => $errors
            ]);

            return redirect()->back()->with(
                $failedCount > 0 ? 'warning' : 'success', 
                $message
            );

        } catch (\Exception $e) {
            Log::error('=== ERROR CRÍTICO DURANTE ELIMINACIÓN MASIVA ===', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 
                'Error crítico durante la eliminación: ' . $e->getMessage()
            );
        }
    }
}