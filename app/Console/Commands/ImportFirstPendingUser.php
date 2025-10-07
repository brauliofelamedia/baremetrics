<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Models\ComparisonRecord;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class ImportFirstPendingUser extends Command
{
    protected $signature = 'import:first-pending-user 
                           {comparison_id : ID de la comparación}
                           {--coupon= : Código de cupón a aplicar}
                           {--membership-active=true : Estado de membresía activa}';
    
    protected $description = 'Importa el primer usuario pendiente con campos personalizados de cupón y membresía activa';

    protected $baremetricsService;
    protected $ghlService;

    public function __construct(BaremetricsService $baremetricsService, GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
        $this->ghlService = $ghlService;
    }

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        $coupon = $this->option('coupon');
        $membershipActive = $this->option('membership-active') === 'true';

        $this->info("🚀 IMPORTACIÓN DE PRIMER USUARIO PENDIENTE");
        $this->info("==========================================");
        $this->info("Comparación ID: {$comparisonId}");
        $this->info("Cupón: " . ($coupon ?: 'No especificado'));
        $this->info("Membresía activa: " . ($membershipActive ? 'Sí' : 'No'));
        $this->newLine();

        try {
            // Buscar la comparación
            $comparison = ComparisonRecord::find($comparisonId);
            if (!$comparison) {
                $this->error("❌ Comparación {$comparisonId} no encontrada");
                return 1;
            }

            // Buscar el primer usuario pendiente
            $pendingUser = $comparison->missingUsers()
                ->where('import_status', 'pending')
                ->first();

            if (!$pendingUser) {
                $this->warn("⚠️ No hay usuarios pendientes en esta comparación");
                return 0;
            }

            $this->info("👤 Usuario encontrado:");
            $this->info("   • Email: {$pendingUser->email}");
            $this->info("   • Nombre: {$pendingUser->name}");
            $this->info("   • Compañía: " . ($pendingUser->company ?: 'N/A'));
            $this->info("   • Teléfono: " . ($pendingUser->phone ?: 'N/A'));
            $this->info("   • Tags: " . ($pendingUser->tags ?: 'N/A'));
            $this->newLine();

            // Configurar Baremetrics para producción
            config(['services.baremetrics.environment' => 'production']);
            $this->baremetricsService->reinitializeConfiguration();

            // Obtener source ID de GHL
            $sourceId = $this->baremetricsService->getGHLSourceId();
            if (!$sourceId) {
                $this->error("❌ No se pudo obtener el source ID de GHL");
                return 1;
            }

            $this->info("🔧 Configuración:");
            $this->info("   • Source ID: {$sourceId}");
            $this->info("   • Entorno: " . config('services.baremetrics.environment'));
            $this->newLine();

            // Marcar como importando
            $pendingUser->markAsImporting();

            // Determinar el plan basado en los tags
            $planData = $this->determinePlanFromTags($pendingUser->tags);
            $this->info("📋 Plan determinado: {$planData['name']} - {$planData['interval']} - \${$planData['amount']}");
            $this->newLine();

            // Crear datos del cliente con campos personalizados
            $customerData = [
                'name' => $pendingUser->name,
                'email' => $pendingUser->email,
                'company' => $pendingUser->company,
                'phone' => $pendingUser->phone,
                'notes' => "Importado desde GHL - Tags: {$pendingUser->tags}",
                'oid' => 'cust_' . uniqid(),
                'properties' => [
                    [
                        'field_id' => '844539743', // GHL: Migrate GHL
                        'value' => 'true'
                    ]
                ]
            ];

            // Agregar campos personalizados si se especifican
            if ($coupon) {
                $customerData['properties'][] = [
                    'field_id' => 'coupon', // ID del campo de cupón (necesitarás obtenerlo)
                    'value' => $coupon
                ];
            }

            if ($membershipActive) {
                $customerData['properties'][] = [
                    'field_id' => 'membership_active', // ID del campo de membresía activa
                    'value' => 'true'
                ];
            }

            // Crear datos de suscripción
            $subscriptionData = [
                'oid' => 'sub_' . uniqid(),
                'started_at' => now()->timestamp,
                'status' => 'active',
                'canceled_at' => null,
                'canceled_reason' => null,
            ];

            $this->info("🔄 Creando cliente en Baremetrics...");
            
            // Crear configuración completa del cliente en Baremetrics
            $result = $this->baremetricsService->createCompleteCustomerSetup(
                $customerData,
                $planData,
                $subscriptionData
            );

            if (!$result || !isset($result['customer']['customer']['oid'])) {
                throw new \Exception('No se pudo crear la configuración completa del cliente en Baremetrics');
            }

            $customerOid = $result['customer']['customer']['oid'];
            $this->info("✅ Cliente creado en Baremetrics con ID: {$customerOid}");

            // Marcar usuario como importado
            $pendingUser->markAsImported($customerOid);

            // Actualizar contacto en GoHighLevel si tiene GHL ID
            if ($pendingUser->ghl_contact_id) {
                $this->info("🔄 Actualizando contacto en GoHighLevel...");
                
                $updateData = [
                    'customFields' => [
                        [
                            'key' => 'GHL: Migrate GHL',
                            'value' => 'true'
                        ]
                    ]
                ];

                if ($coupon) {
                    $updateData['customFields'][] = [
                        'key' => 'Coupon',
                        'value' => $coupon
                    ];
                }

                if ($membershipActive) {
                    $updateData['customFields'][] = [
                        'key' => 'Membership Active',
                        'value' => 'true'
                    ];
                }

                try {
                    $ghlResult = $this->ghlService->updateContact($pendingUser->ghl_contact_id, $updateData);
                    $this->info("✅ Contacto actualizado en GoHighLevel");
                } catch (\Exception $e) {
                    $this->warn("⚠️ Error actualizando contacto en GoHighLevel: " . $e->getMessage());
                }
            }

            $this->newLine();
            $this->info("🎉 IMPORTACIÓN COMPLETADA EXITOSAMENTE");
            $this->info("=====================================");
            $this->info("✅ Usuario: {$pendingUser->email}");
            $this->info("✅ Plan: {$planData['name']}");
            $this->info("✅ Customer ID: {$customerOid}");
            $this->info("✅ Estado: Importado");
            
            if ($coupon) {
                $this->info("✅ Cupón aplicado: {$coupon}");
            }
            
            if ($membershipActive) {
                $this->info("✅ Membresía activa: Sí");
            }

            Log::info('Primer usuario pendiente importado exitosamente', [
                'user_id' => $pendingUser->id,
                'email' => $pendingUser->email,
                'customer_oid' => $customerOid,
                'plan' => $planData,
                'coupon' => $coupon,
                'membership_active' => $membershipActive,
                'result' => $result
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            
            if (isset($pendingUser)) {
                $pendingUser->markAsFailed($e->getMessage());
            }

            Log::error('Error importando primer usuario pendiente', [
                'comparison_id' => $comparisonId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
    }

    /**
     * Determinar el plan basado en los tags del usuario
     */
    private function determinePlanFromTags(?string $tags): array
    {
        if (empty($tags)) {
            return [
                'name' => 'Plan Básico',
                'interval' => 'month',
                'amount' => 29.99,
                'currency' => 'USD'
            ];
        }

        $tagsArray = explode(',', $tags);
        $tagsArray = array_map('trim', $tagsArray);

        // Lógica para determinar el plan basado en tags
        if (in_array('VIP', $tagsArray) || in_array('Premium', $tagsArray)) {
            return [
                'name' => 'Plan Premium',
                'interval' => 'month',
                'amount' => 99.99,
                'currency' => 'USD'
            ];
        } elseif (in_array('Pro', $tagsArray) || in_array('Professional', $tagsArray)) {
            return [
                'name' => 'Plan Pro',
                'interval' => 'month',
                'amount' => 59.99,
                'currency' => 'USD'
            ];
        } else {
            return [
                'name' => 'Plan Básico',
                'interval' => 'month',
                'amount' => 29.99,
                'currency' => 'USD'
            ];
        }
    }
}
