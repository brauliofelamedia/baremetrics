<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Models\ComparisonRecord;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class ImportAllPendingUsers extends Command
{
    protected $signature = 'import:all-pending-users 
                           {comparison_id : ID de la comparación}
                           {--plan-id= : ID del plan específico a usar (opcional)}
                           {--batch-size=10 : Número de usuarios a procesar por lote}
                           {--dry-run : Solo mostrar qué se importaría sin hacer cambios}';
    
    protected $description = 'Importa todos los usuarios pendientes con planes existentes, campos personalizados y historial de pagos';

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
        $planId = $this->option('plan-id');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 IMPORTACIÓN MASIVA DE USUARIOS PENDIENTES");
        $this->info("=============================================");
        $this->info("Comparación ID: {$comparisonId}");
        $this->info("Plan ID: " . ($planId ?: 'Auto-determinar por tags'));
        $this->info("Tamaño de lote: {$batchSize}");
        $this->info("Modo: " . ($dryRun ? "DRY RUN (solo simulación)" : "IMPORTACIÓN REAL"));
        $this->newLine();

        try {
            // Buscar la comparación
            $comparison = ComparisonRecord::find($comparisonId);
            if (!$comparison) {
                $this->error("❌ Comparación {$comparisonId} no encontrada");
                return 1;
            }

            // Buscar usuarios pendientes
            $pendingUsers = $comparison->missingUsers()
                ->where('import_status', 'pending')
                ->get();

            if ($pendingUsers->isEmpty()) {
                $this->warn("⚠️ No hay usuarios pendientes en esta comparación");
                return 0;
            }

            $this->info("👥 Usuarios pendientes encontrados: {$pendingUsers->count()}");
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

            // Obtener planes existentes
            $existingPlans = $this->baremetricsService->getPlans($sourceId);
            $plansMap = [];
            
            if ($existingPlans && isset($existingPlans['plans'])) {
                foreach ($existingPlans['plans'] as $plan) {
                    $plansMap[$plan['name']] = $plan;
                }
                $this->info("📋 Planes disponibles: " . count($plansMap));
            }

            $imported = 0;
            $failed = 0;
            $batches = array_chunk($pendingUsers->toArray(), $batchSize);
            $totalBatches = count($batches);

            $this->info("🔄 Procesando {$totalBatches} lotes de {$batchSize} usuarios cada uno...");
            $this->newLine();

            foreach ($batches as $batchIndex => $batch) {
                $batchNumber = $batchIndex + 1;
                $this->info("📦 Procesando lote {$batchNumber}/{$totalBatches}");
                
                foreach ($batch as $userData) {
                    $user = MissingUser::find($userData['id']);
                    
                    try {
                        $this->info("   👤 Procesando: {$user->email} ({$user->name})");
                        
                        if ($dryRun) {
                            $this->info("      🔍 DRY RUN: Se importaría con plan y campos personalizados");
                            continue;
                        }

                        // Marcar como importando
                        $user->markAsImporting();

                        // Determinar plan basado en tags o usar el especificado
                        $planData = $this->determinePlanFromTags($user->tags, $plansMap, $planId);
                        
                        if (!$planData) {
                            throw new \Exception("No se pudo determinar el plan para el usuario");
                        }

                        $this->info("      📋 Plan asignado: {$planData['name']} - \${$planData['amount']}");

                        // Crear datos del cliente con campos personalizados completos
                        $customerData = [
                            'name' => $user->name,
                            'email' => $user->email,
                            'company' => $user->company,
                            'phone' => $user->phone,
                            'notes' => "Importado desde GHL - Tags: {$user->tags} - Fecha: " . now()->format('Y-m-d H:i:s'),
                            'oid' => 'cust_' . uniqid(),
                            'properties' => [
                                [
                                    'field_id' => '844539743', // GHL: Migrate GHL
                                    'value' => 'true'
                                ]
                            ]
                        ];

                        // Agregar campos personalizados adicionales basados en tags
                        $this->addCustomFieldsFromTags($customerData, $user->tags);

                        // Crear datos de suscripción
                        $subscriptionData = [
                            'oid' => 'sub_' . uniqid(),
                            'started_at' => $this->determineStartDate($user),
                            'status' => 'active',
                            'canceled_at' => null,
                            'canceled_reason' => null,
                        ];

                        // Si es un plan existente, usar método directo
                        if (isset($planData['existing_plan']) && $planData['existing_plan']) {
                            $result = $this->createCustomerWithExistingPlan($customerData, $planData, $subscriptionData, $sourceId);
                        } else {
                            // Crear configuración completa del cliente en Baremetrics
                            $result = $this->baremetricsService->createCompleteCustomerSetup(
                                $customerData,
                                $planData,
                                $subscriptionData
                            );
                        }

                        if (!$result || !isset($result['customer']['customer']['oid'])) {
                            throw new \Exception('No se pudo crear la configuración completa del cliente en Baremetrics');
                        }

                        $customerOid = $result['customer']['customer']['oid'];
                        
                        // Crear historial de pagos si es necesario
                        $this->createPaymentHistory($user, $customerOid, $planData, $sourceId);

                        // Marcar usuario como importado
                        $user->markAsImported($customerOid);

                        // Actualizar contacto en GoHighLevel si tiene GHL ID
                        if ($user->ghl_contact_id) {
                            $this->updateGHLContact($user);
                        }

                        $imported++;
                        $this->info("      ✅ Importado exitosamente (ID: {$customerOid})");

                    } catch (\Exception $e) {
                        $failed++;
                        $user->markAsFailed($e->getMessage());
                        $this->error("      ❌ Error: " . $e->getMessage());
                        
                        Log::error('Error importando usuario pendiente', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }

                    // Pausa pequeña entre usuarios
                    usleep(100000); // 100ms
                }

                $this->newLine();
                
                // Pausa entre lotes
                if ($batchNumber < $totalBatches) {
                    $this->info("⏳ Pausa de 2 segundos entre lotes...");
                    sleep(2);
                }
            }

            $this->newLine();
            $this->info("🎉 IMPORTACIÓN MASIVA COMPLETADA");
            $this->info("================================");
            $this->info("✅ Usuarios importados: {$imported}");
            $this->info("❌ Usuarios fallaron: {$failed}");
            $this->info("📊 Total procesados: " . ($imported + $failed));

            Log::info('Importación masiva de usuarios pendientes completada', [
                'comparison_id' => $comparisonId,
                'imported' => $imported,
                'failed' => $failed,
                'total' => $pendingUsers->count(),
                'plan_id' => $planId,
                'batch_size' => $batchSize,
                'dry_run' => $dryRun
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error crítico durante la importación: " . $e->getMessage());
            
            Log::error('Error crítico en importación masiva', [
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
    private function determinePlanFromTags(?string $tags, array $plansMap, ?string $planId): ?array
    {
        // Si se especifica un plan ID, usarlo
        if ($planId) {
            foreach ($plansMap as $plan) {
                if ($plan['oid'] === $planId) {
                    return [
                        'name' => $plan['name'],
                        'interval' => $plan['interval'],
                        'amount' => $plan['amounts'][0]['amount'] ?? 0,
                        'currency' => $plan['amounts'][0]['currency'] ?? 'USD',
                        'oid' => $plan['oid']
                    ];
                }
            }
        }

        if (empty($tags)) {
            // Usar plan básico por defecto
            return [
                'name' => 'Plan Básico',
                'interval' => 'month',
                'amount' => 29,
                'currency' => 'USD',
                'oid' => 'plan_68e557d752451'
            ];
        }

        $tagsArray = explode(',', $tags);
        $tagsArray = array_map('trim', $tagsArray);

        // Buscar planes existentes basados en tags
        foreach ($tagsArray as $tag) {
            if (isset($plansMap[$tag])) {
                $plan = $plansMap[$tag];
                return [
                    'name' => $plan['name'],
                    'interval' => $plan['interval'],
                    'amount' => $plan['amounts'][0]['amount'] ?? 0,
                    'currency' => $plan['amounts'][0]['currency'] ?? 'USD',
                    'oid' => $plan['oid'],
                    'existing_plan' => true // Marcar como plan existente
                ];
            }
        }

        // Si no encuentra coincidencia, usar plan básico
        return [
            'name' => 'Plan Básico',
            'interval' => 'month',
            'amount' => 29,
            'currency' => 'USD',
            'oid' => 'plan_68e557d752451'
        ];
    }

    /**
     * Agregar campos personalizados basados en tags
     */
    private function addCustomFieldsFromTags(array &$customerData, ?string $tags): void
    {
        if (empty($tags)) return;

        $tagsArray = explode(',', $tags);
        $tagsArray = array_map('trim', $tagsArray);

        // Agregar campos personalizados basados en tags específicos
        foreach ($tagsArray as $tag) {
            switch ($tag) {
                case 'creetelo_anual':
                case 'créetelo_anual':
                    $customerData['properties'][] = [
                        'field_id' => 'membership_type',
                        'value' => 'annual'
                    ];
                    $customerData['properties'][] = [
                        'field_id' => 'membership_active',
                        'value' => 'true'
                    ];
                    break;
                    
                case 'creetelo_mensual':
                case 'créetelo_mensual':
                    $customerData['properties'][] = [
                        'field_id' => 'membership_type',
                        'value' => 'monthly'
                    ];
                    $customerData['properties'][] = [
                        'field_id' => 'membership_active',
                        'value' => 'true'
                    ];
                    break;
                    
                case 'directorio':
                    $customerData['properties'][] = [
                        'field_id' => 'directory_access',
                        'value' => 'true'
                    ];
                    break;
                    
                case 'upsell_acbi_creetelo':
                    $customerData['properties'][] = [
                        'field_id' => 'upsell_completed',
                        'value' => 'true'
                    ];
                    break;
            }
        }
    }

    /**
     * Determinar fecha de inicio basada en datos del usuario
     */
    private function determineStartDate(MissingUser $user): int
    {
        // Si tiene fecha de importación, usar esa
        if ($user->imported_at) {
            return $user->imported_at->timestamp;
        }

        // Si tiene fecha de creación, usar esa
        if ($user->created_at) {
            return $user->created_at->timestamp;
        }

        // Por defecto, usar fecha actual
        return now()->timestamp;
    }

    /**
     * Crear historial de pagos para el usuario
     */
    private function createPaymentHistory(MissingUser $user, string $customerOid, array $planData, string $sourceId): void
    {
        try {
            // Obtener historial de pagos de GoHighLevel si está disponible
            if ($user->ghl_contact_id) {
                $payments = $this->ghlService->getContactPayments($user->ghl_contact_id);
                
                if ($payments && isset($payments['payments'])) {
                    foreach ($payments['payments'] as $payment) {
                        // Crear evento de pago en Baremetrics
                        $paymentData = [
                            'oid' => 'pay_' . uniqid(),
                            'customer_oid' => $customerOid,
                            'amount' => $payment['amount'] ?? $planData['amount'],
                            'currency' => $payment['currency'] ?? $planData['currency'],
                            'occurred_at' => $payment['date'] ?? now()->timestamp,
                            'status' => 'successful'
                        ];

                        // Aquí podrías agregar lógica para crear eventos de pago en Baremetrics
                        // Por ahora solo logueamos
                        Log::info('Payment history found for user', [
                            'user_email' => $user->email,
                            'customer_oid' => $customerOid,
                            'payment_data' => $paymentData
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error obteniendo historial de pagos', [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar contacto en GoHighLevel
     */
    private function updateGHLContact(MissingUser $user): void
    {
        try {
            $updateData = [
                'customFields' => [
                    [
                        'key' => 'GHL: Migrate GHL',
                        'value' => 'true'
                    ],
                    [
                        'key' => 'Membership Active',
                        'value' => 'true'
                    ]
                ]
            ];

            $this->ghlService->updateContact($user->ghl_contact_id, $updateData);
            
            Log::info('Contacto actualizado en GoHighLevel', [
                'user_email' => $user->email,
                'ghl_contact_id' => $user->ghl_contact_id
            ]);
        } catch (\Exception $e) {
            Log::warning('Error actualizando contacto en GoHighLevel', [
                'user_email' => $user->email,
                'ghl_contact_id' => $user->ghl_contact_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Crear cliente con plan existente (sin intentar crear el plan)
     */
    private function createCustomerWithExistingPlan(array $customerData, array $planData, array $subscriptionData, string $sourceId): ?array
    {
        try {
            // Crear customer
            $customer = $this->baremetricsService->createCustomer($customerData, $sourceId);
            if (!$customer || !isset($customer['customer']['oid'])) {
                Log::error('Baremetrics - Failed to create customer');
                return null;
            }

            // Usar plan existente directamente
            $plan = $planData;

            // Add customer and plan OIDs to subscription data
            $subscriptionData['customer_oid'] = $customer['customer']['oid'];
            $subscriptionData['plan_oid'] = $plan['oid'];

            // Create subscription
            $subscription = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);
            if (!$subscription) {
                Log::error('Baremetrics - Failed to create subscription');
                return null;
            }

            Log::info('Baremetrics Customer with Existing Plan Created Successfully', [
                'source_id' => $sourceId,
                'customer_oid' => $customer['customer']['oid'],
                'plan_oid' => $plan['oid'],
                'subscription' => $subscription
            ]);

            return [
                'customer' => $customer,
                'plan' => $plan,
                'subscription' => $subscription,
                'source_id' => $sourceId
            ];

        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Customer with Existing Plan Setup', [
                'customer_data' => $customerData,
                'plan_data' => $planData,
                'subscription_data' => $subscriptionData,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
