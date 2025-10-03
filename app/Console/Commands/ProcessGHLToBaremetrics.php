<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ProcessGHLToBaremetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:process-ghl-to-baremetrics 
                           {--limit= : Límite de usuarios a procesar (opcional)}
                           {--delay=2 : Delay entre requests en segundos (default: 2)}
                           {--batch-size=50 : Procesar en lotes de N usuarios (default: 50)}
                           {--batch-delay=10 : Delay entre lotes en segundos (default: 10)}
                           {--dry-run : Ejecutar sin hacer cambios reales}
                           {--email= : Correo para notificaciones (opcional)}
                           {--active-only : Solo usuarios activos (default: true)}
                           {--with-subscription : Solo usuarios con suscripción activa (default: true)}
                           {--no-filters : Desactivar todos los filtros (procesar todos los usuarios)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa usuarios de GoHighLevel y los compara/actualiza en Baremetrics';

    protected $ghlService;
    protected $baremetricsService;

    public function __construct(GoHighLevelService $ghlService, BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = now();
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $notificationEmail = $this->option('email') ?? config('services.gohighlevel.notification_email');
        $delay = (float) $this->option('delay');
        $batchSize = (int) $this->option('batch-size');
        $batchDelay = (int) $this->option('batch-delay');
        $activeOnly = $this->option('active-only');
        $withSubscription = $this->option('with-subscription');
        $noFilters = $this->option('no-filters');

        $this->info('🔄 Procesando usuarios de GoHighLevel hacia Baremetrics...');
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        if ($notificationEmail) {
            $this->info("📧 Correo de notificación: {$notificationEmail}");
        }

        $this->info("⏱️  Configuración de delays:");
        $this->info("   • Delay entre requests: {$delay} segundos");
        $this->info("   • Tamaño de lote: {$batchSize} usuarios");
        $this->info("   • Delay entre lotes: {$batchDelay} segundos");

        $this->info("🔍 Configuración de filtros:");
        if ($noFilters) {
            $this->info("   • Sin filtros: Procesando TODOS los usuarios");
        } else {
            $this->info("   • Solo usuarios activos: " . ($activeOnly ? 'SÍ' : 'NO'));
            $this->info("   • Solo con suscripción activa: " . ($withSubscription ? 'SÍ' : 'NO'));
        }

        // Inicializar estadísticas
        $stats = [
            'total_processed' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'not_found_in_baremetrics' => 0,
            'rate_limited' => 0,
            'server_errors' => 0,
            'errors' => [],
            'missing_in_baremetrics' => [], // Lista de usuarios que no existen en Baremetrics
            'start_time' => $startTime,
            'is_dry_run' => $isDryRun
        ];

        try {
            // Obtener todos los usuarios de GoHighLevel
            $this->info('📥 Obteniendo usuarios de GoHighLevel...');
            $ghlUsers = $this->getAllGHLUsers($limit, $activeOnly, $withSubscription, $noFilters);
            
            if (empty($ghlUsers)) {
                $this->error('❌ No se encontraron usuarios en GoHighLevel con los filtros aplicados');
                return 1;
            }

            $totalUsers = count($ghlUsers);
            $this->info("✅ Se encontraron {$totalUsers} usuarios en GoHighLevel con los filtros aplicados");

            $stats['total_processed'] = $totalUsers;

            // Crear barra de progreso
            $progressBar = $this->output->createProgressBar($stats['total_processed']);
            $progressBar->start();

            // Procesar en lotes
            $batches = array_chunk($ghlUsers, $batchSize);
            $totalBatches = count($batches);
            
            $this->newLine();
            $this->info("📦 Procesando {$totalBatches} lotes de {$batchSize} usuarios cada uno");

            foreach ($batches as $batchIndex => $batch) {
                $batchNumber = $batchIndex + 1;
                $this->info("🔄 Procesando lote {$batchNumber}/{$totalBatches}...");
                
                foreach ($batch as $userIndex => $user) {
                    $email = $user['email'] ?? null;
                    
                    if (!$email) {
                        $stats['errors'][] = "Usuario sin email (ID: {$user['id']})";
                        $progressBar->advance();
                        continue;
                    }

                    try {
                        $result = $this->processUser($email, $user, $isDryRun);
                        
                        if ($result['success']) {
                            $stats['successful_updates']++;
                        } else {
                            $stats['failed_updates']++;
                            $stats['errors'][] = "Error procesando {$email}: " . $result['error'];
                            
                            // Contar tipos específicos de errores
                            if (isset($result['rate_limited'])) {
                                $stats['rate_limited']++;
                            }
                            if (isset($result['server_error'])) {
                                $stats['server_errors']++;
                            }
                            if (isset($result['not_found_in_baremetrics'])) {
                                $stats['not_found_in_baremetrics']++;
                                
                                // Obtener información adicional de membresía y cupones
                                $membershipInfo = $this->getMembershipInfo($user['id']);
                                $subscriptionInfo = $this->getSubscriptionInfo($user['id']);
                                
                                $stats['missing_in_baremetrics'][] = [
                                    'email' => $email,
                                    'ghl_id' => $user['id'],
                                    'name' => $user['firstName'] . ' ' . $user['lastName'],
                                    'phone' => $user['phone'] ?? null,
                                    'created_at' => $user['dateAdded'] ?? null,
                                    'membership' => $membershipInfo,
                                    'subscription' => $subscriptionInfo,
                                    'custom_fields' => $this->getCustomFieldsInfo($user)
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        $stats['failed_updates']++;
                        $stats['errors'][] = "Excepción procesando {$email}: " . $e->getMessage();
                        Log::error('Error procesando usuario GHL', [
                            'email' => $email,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }

                    $progressBar->advance();
                    
                    // Delay entre requests (excepto el último del lote)
                    if ($userIndex < count($batch) - 1) {
                        $this->sleepWithProgress($delay);
                    }
                }
                
                // Delay entre lotes (excepto el último lote)
                if ($batchIndex < $totalBatches - 1) {
                    $this->info("⏸️  Pausa entre lotes: {$batchDelay} segundos...");
                    $this->sleepWithProgress($batchDelay);
                }
            }

            $progressBar->finish();
            $this->newLine();

            $stats['end_time'] = now();
            $stats['duration'] = $stats['end_time']->diffInMinutes($stats['start_time']);

            // Mostrar resumen
            $this->displaySummary($stats);

            // Guardar lista de usuarios faltantes en Baremetrics
            $this->saveMissingUsersReport($stats['missing_in_baremetrics']);

            // Enviar correo de notificación
            if ($notificationEmail) {
                $this->sendNotificationEmail($stats, $notificationEmail);
            }

        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            Log::error('Error en procesamiento GHL a Baremetrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Obtener todos los usuarios de GoHighLevel
     */
    private function getAllGHLUsers($limit = null, $activeOnly = true, $withSubscription = true, $noFilters = false)
    {
        try {
            $allUsers = [];
            $page = 1;
            $hasMore = true;
            $filteredCount = 0;
            $totalProcessed = 0;

            $this->info("🔍 Aplicando filtros: " . ($noFilters ? 'NINGUNO' : 'Activos + Suscripción activa'));

            while ($hasMore) {
                $response = $this->ghlService->getContacts('', $page);
                
                if (!$response || empty($response['contacts'])) {
                    break;
                }

                $contacts = $response['contacts'];
                $totalProcessed += count($contacts);

                // Aplicar filtros si no se desactivan
                if (!$noFilters) {
                    $filteredContacts = [];
                    
                    foreach ($contacts as $contact) {
                        $shouldInclude = true;
                        
                        // Filtro 1: Solo usuarios activos
                        if ($activeOnly && isset($contact['status']) && $contact['status'] !== 'active') {
                            $shouldInclude = false;
                        }
                        
                        // Filtro 2: Solo usuarios con suscripción activa
                        if ($shouldInclude && $withSubscription) {
                            try {
                                $subscription = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
                                if (!$subscription || ($subscription['status'] ?? '') !== 'active') {
                                    $shouldInclude = false;
                                }
                            } catch (\Exception $e) {
                                // Si hay error obteniendo suscripción, excluir el usuario
                                $shouldInclude = false;
                            }
                        }
                        
                        if ($shouldInclude) {
                            $filteredContacts[] = $contact;
                            $filteredCount++;
                        }
                    }
                    
                    $contacts = $filteredContacts;
                } else {
                    $filteredCount += count($contacts);
                }

                if (!empty($contacts)) {
                    $allUsers = array_merge($allUsers, $contacts);
                }

                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;
                usleep(100000); // Pequeña pausa entre requests
                
                // Aplicar límite si se especifica
                if ($limit && count($allUsers) >= $limit) {
                    $allUsers = array_slice($allUsers, 0, $limit);
                    break;
                }

                // Mostrar progreso cada 1000 usuarios procesados
                if ($totalProcessed % 1000 === 0) {
                    $this->info("📊 Procesados: {$totalProcessed} usuarios, Filtrados: {$filteredCount} usuarios");
                }
            }

            $this->info("📊 RESUMEN DE FILTRADO:");
            $this->info("   • Total procesados: {$totalProcessed} usuarios");
            $this->info("   • Usuarios que pasaron filtros: {$filteredCount} usuarios");
            $this->info("   • Usuarios a procesar: " . count($allUsers) . " usuarios");
            
            return $allUsers;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios de GoHighLevel', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Procesa un usuario individual
     */
    private function processUser($email, $ghlUser, $isDryRun = false)
    {
        try {
            // Buscar usuario en Baremetrics
            $baremetricsUsers = $this->baremetricsService->getCustomersByEmail($email);
            
            if (empty($baremetricsUsers)) {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado en Baremetrics',
                    'not_found_in_baremetrics' => true
                ];
            }

            $baremetricsUser = $baremetricsUsers[0]; // Tomar el primero
            
            // Obtener campos personalizados de GHL
            $customFields = collect($ghlUser['customFields'] ?? []);
            
            // Obtener datos de suscripción más reciente
            $subscription = $this->ghlService->getSubscriptionStatusByContact($ghlUser['id']);
            $couponCode = $subscription['couponCode'] ?? null;
            $subscriptionStatus = $subscription['status'] ?? 'none';
            
            // Preparar datos para Baremetrics
            $ghlData = [
                'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
                'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
                'country' => $ghlUser['country'] ?? '-',
                'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
                'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
                'state' => $ghlUser['state'] ?? '-',
                'location' => $ghlUser['city'] ?? '-',
                'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
                'subscriptions' => $subscriptionStatus,
                'coupon_code' => $couponCode
            ];

            if ($isDryRun) {
                Log::info('DRY-RUN: Datos que se actualizarían en Baremetrics', [
                    'email' => $email,
                    'ghl_id' => $ghlUser['id'],
                    'baremetrics_oid' => $baremetricsUser['oid'],
                    'ghl_data' => $ghlData
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Simulación exitosa'
                ];
            }

            // Actualizar en Baremetrics con manejo de rate limiting
            try {
                $result = $this->baremetricsService->updateCustomerAttributes($baremetricsUser['oid'], $ghlData);
                
                if ($result) {
                    Log::info('Usuario actualizado exitosamente desde GHL', [
                        'email' => $email,
                        'ghl_id' => $ghlUser['id'],
                        'baremetrics_oid' => $baremetricsUser['oid']
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Actualización exitosa'
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Error actualizando en Baremetrics'
                    ];
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                
                if ($statusCode === 429) {
                    // Rate limiting - esperar más tiempo
                    Log::warning('Rate limiting detectado en Baremetrics', [
                        'email' => $email,
                        'status_code' => $statusCode
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Rate limiting en Baremetrics - requiere delay mayor',
                        'rate_limited' => true
                    ];
                } elseif ($statusCode >= 500) {
                    // Error del servidor - reintentar más tarde
                    Log::warning('Error del servidor en Baremetrics', [
                        'email' => $email,
                        'status_code' => $statusCode
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => "Error del servidor Baremetrics ({$statusCode})",
                        'server_error' => true
                    ];
                } else {
                    throw $e; // Re-lanzar otros errores
                }
            }

        } catch (\Exception $e) {
            Log::error('Error procesando usuario GHL', [
                'email' => $email,
                'ghl_id' => $ghlUser['id'] ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sleep con indicador de progreso
     */
    private function sleepWithProgress($seconds)
    {
        if ($seconds <= 0) return;
        
        $dots = 0;
        $maxDots = 10;
        $interval = $seconds / $maxDots;
        
        for ($i = 0; $i < $maxDots; $i++) {
            sleep($interval);
            $this->output->write('.');
        }
        $this->output->write(' ');
    }

    /**
     * Muestra resumen de estadísticas
     */
    private function displaySummary($stats)
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE PROCESAMIENTO GHL → BAREMETRICS');
        $this->info('============================================');
        $this->line("⏱️  Duración: {$stats['duration']} minutos");
        $this->line("📈 Total procesados: {$stats['total_processed']}");
        $this->line("✅ Actualizaciones exitosas: {$stats['successful_updates']}");
        $this->line("❌ Actualizaciones fallidas: {$stats['failed_updates']}");
        $this->line("🔍 No encontrados en Baremetrics: {$stats['not_found_in_baremetrics']}");
        
        if ($stats['rate_limited'] > 0) {
            $this->line("🚫 Rate limited: {$stats['rate_limited']}");
        }
        
        if ($stats['server_errors'] > 0) {
            $this->line("🔧 Errores de servidor: {$stats['server_errors']}");
        }
        
        $successRate = $stats['total_processed'] > 0 ? 
            round(($stats['successful_updates'] / $stats['total_processed']) * 100, 2) : 0;
        $this->line("📊 Tasa de éxito: {$successRate}%");

        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->warn('⚠️  PRIMEROS ERRORES:');
            $errorCount = min(5, count($stats['errors']));
            for ($i = 0; $i < $errorCount; $i++) {
                $this->line("• {$stats['errors'][$i]}");
            }
            if (count($stats['errors']) > 5) {
                $this->line("• ... y " . (count($stats['errors']) - 5) . " errores más");
            }
        }

        // Mostrar información sobre usuarios faltantes
        if (!empty($stats['missing_in_baremetrics'])) {
            $this->newLine();
            $this->info('📋 USUARIOS FALTANTES EN BAREMETRICS:');
            $this->line("• Total usuarios en GHL no encontrados en Baremetrics: " . count($stats['missing_in_baremetrics']));
            $this->line("• Archivo generado: storage/app/ghl-missing-users-" . now()->format('Y-m-d-H-i-s') . ".json");
        }
    }

    /**
     * Guarda reporte de usuarios faltantes en Baremetrics
     */
    private function saveMissingUsersReport($missingUsers)
    {
        if (empty($missingUsers)) {
            return;
        }

        $filename = 'ghl-missing-users-' . now()->format('Y-m-d-H-i-s') . '.json';
        $filepath = storage_path('app/' . $filename);
        
        $report = [
            'generated_at' => now()->toISOString(),
            'total_missing_users' => count($missingUsers),
            'description' => 'Usuarios que existen en GoHighLevel pero no en Baremetrics',
            'users' => $missingUsers
        ];

        File::put($filepath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("📄 Reporte de usuarios faltantes guardado en: {$filename}");
        
        Log::info('Reporte de usuarios faltantes generado', [
            'filename' => $filename,
            'total_users' => count($missingUsers)
        ]);
    }

    /**
     * Envía correo de notificación con el estado
     */
    private function sendNotificationEmail($stats, $email)
    {
        try {
            $subject = 'Reporte de Procesamiento GHL → Baremetrics - ' . now()->format('Y-m-d H:i:s');
            
            $data = [
                'stats' => $stats,
                'subject' => $subject,
                'is_dry_run' => $stats['is_dry_run'],
                'processing_type' => 'GHL_TO_BAREMETRICS'
            ];

            \Mail::send('emails.ghl-processing-report', $data, function ($message) use ($email, $subject) {
                $message->to($email)
                        ->subject($subject);
            });

            $this->info("📧 Correo de notificación enviado a: {$email}");
            
        } catch (\Exception $e) {
            $this->error("❌ Error enviando correo: " . $e->getMessage());
            Log::error('Error enviando correo de notificación', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener información de membresía
     */
    private function getMembershipInfo($contactId)
    {
        try {
            $membership = $this->ghlService->getContactMembership($contactId);
            
            if (!$membership || empty($membership['memberships'])) {
                return [
                    'has_membership' => false,
                    'status' => null,
                    'membership_id' => null,
                    'created_at' => null
                ];
            }

            // Obtener la membresía más reciente
            $latestMembership = null;
            foreach ($membership['memberships'] as $m) {
                if (!$latestMembership || 
                    (isset($m['createdAt']) && isset($latestMembership['createdAt']) && 
                     strtotime($m['createdAt']) > strtotime($latestMembership['createdAt']))) {
                    $latestMembership = $m;
                }
            }

            return [
                'has_membership' => true,
                'status' => $latestMembership['status'] ?? null,
                'membership_id' => $latestMembership['id'] ?? null,
                'created_at' => $latestMembership['createdAt'] ?? null,
                'total_memberships' => count($membership['memberships'])
            ];

        } catch (\Exception $e) {
            Log::warning('Error obteniendo información de membresía', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'has_membership' => false,
                'status' => null,
                'membership_id' => null,
                'created_at' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información de suscripción
     */
    private function getSubscriptionInfo($contactId)
    {
        try {
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
            
            if (!$subscription) {
                return [
                    'has_subscription' => false,
                    'status' => null,
                    'subscription_id' => null,
                    'coupon_code' => null,
                    'created_at' => null
                ];
            }

            return [
                'has_subscription' => true,
                'status' => $subscription['status'] ?? null,
                'subscription_id' => $subscription['id'] ?? null,
                'coupon_code' => $subscription['couponCode'] ?? null,
                'created_at' => $subscription['createdAt'] ?? null,
                'price' => $subscription['price'] ?? null,
                'currency' => $subscription['currency'] ?? null,
                'frequency' => $subscription['frequency'] ?? null
            ];

        } catch (\Exception $e) {
            Log::warning('Error obteniendo información de suscripción', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'has_subscription' => false,
                'status' => null,
                'subscription_id' => null,
                'coupon_code' => null,
                'created_at' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información de campos personalizados
     */
    private function getCustomFieldsInfo($user)
    {
        $customFields = collect($user['customFields'] ?? []);
        
        return [
            'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? null,
            'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? null,
            'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? null,
            'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? null,
            'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? null,
            'country' => $user['country'] ?? null,
            'state' => $user['state'] ?? null,
            'city' => $user['city'] ?? null
        ];
    }
}
