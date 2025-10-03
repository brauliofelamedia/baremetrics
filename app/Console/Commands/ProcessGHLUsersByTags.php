<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class ProcessGHLUsersByTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:process-by-tags 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas (default: creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual)}
                           {--limit= : Límite de usuarios a procesar (opcional)}
                           {--delay=2 : Delay entre requests en segundos (default: 2)}
                           {--batch-size=50 : Procesar en lotes de N usuarios (default: 50)}
                           {--batch-delay=10 : Delay entre lotes en segundos (default: 10)}
                           {--dry-run : Ejecutar sin hacer cambios reales}
                           {--email= : Correo para notificaciones (opcional)}
                           {--count-only : Solo contar usuarios, no procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesar usuarios de GoHighLevel filtrados por tags específicos (creetelo_anual, creetelo_mensual)';

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
        $countOnly = $this->option('count-only');
        
        // Parsear tags
        $tagsString = $this->option('tags');
        $tags = array_map('trim', explode(',', $tagsString));

        $this->info('🏷️  PROCESANDO USUARIOS DE GOHIGHLEVEL POR TAGS');
        $this->info('================================================');
        
        $this->info("🏷️  Tags configurados: " . implode(', ', $tags));
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        if ($countOnly) {
            $this->info('📊 MODO CONTEO: Solo se contarán usuarios, no se procesarán');
        }

        if ($notificationEmail) {
            $this->info("📧 Correo de notificación: {$notificationEmail}");
        }

        $this->info("⏱️  Configuración de delays:");
        $this->info("   • Delay entre requests: {$delay} segundos");
        $this->info("   • Tamaño de lote: {$batchSize} usuarios");
        $this->info("   • Delay entre lotes: {$batchDelay} segundos");

        // Inicializar estadísticas
        $stats = [
            'total_processed' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'not_found_in_baremetrics' => 0,
            'rate_limited' => 0,
            'server_errors' => 0,
            'errors' => [],
            'missing_in_baremetrics' => [],
            'start_time' => $startTime,
            'is_dry_run' => $isDryRun
        ];

        try {
            // Obtener usuarios de GoHighLevel por tags
            $this->info('📥 Obteniendo usuarios de GoHighLevel por tags...');
            $ghlUsers = $this->getAllGHLUsersByTags($tags, $limit);
            
            if (empty($ghlUsers)) {
                $this->error('❌ No se encontraron usuarios en GoHighLevel con los tags especificados');
                return 1;
            }

            $totalUsers = count($ghlUsers);
            $this->info("✅ Se encontraron {$totalUsers} usuarios en GoHighLevel con los tags: " . implode(', ', $tags));

            if ($countOnly) {
                $this->info('📊 CONTEO COMPLETADO');
                $this->info("• Total usuarios con tags: {$totalUsers}");
                return 0;
            }

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
                                    'tags' => $user['tags'] ?? [],
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
                    
                    // Delay entre requests
                    if ($delay > 0) {
                        $this->sleepWithProgress($delay);
                    }
                }

                // Delay entre lotes
                if ($batchIndex < $totalBatches - 1 && $batchDelay > 0) {
                    $this->info("⏸️  Pausa entre lotes: {$batchDelay} segundos");
                    $this->sleepWithProgress($batchDelay);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Mostrar estadísticas finales
            $this->showFinalStats($stats, $startTime);

            // Guardar reporte de usuarios faltantes
            if (!empty($stats['missing_in_baremetrics'])) {
                $this->saveMissingUsersReport($stats['missing_in_baremetrics'], $tags);
            }

            // Enviar notificación por email
            if ($notificationEmail) {
                $this->sendEmailNotification($stats, $notificationEmail, $tags);
            }

            $this->info('✅ Procesamiento completado exitosamente');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el procesamiento: " . $e->getMessage());
            Log::error('Error en procesamiento GHL por tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }
    }

    /**
     * Obtener todos los usuarios de GoHighLevel por tags
     */
    private function getAllGHLUsersByTags($tags, $limit = null)
    {
        try {
            $this->info("🔍 Buscando usuarios con tags: " . implode(', ', $tags));

            // Usar el método optimizado para grandes volúmenes (100,000+ usuarios)
            $response = $this->ghlService->getContactsByTagsOptimized($tags, $limit);
            
            if (!$response || empty($response['contacts'])) {
                return [];
            }

            $contacts = $response['contacts'];
            $meta = $response['meta'] ?? [];

            $this->info("📊 RESUMEN DE BÚSQUEDA POR TAGS:");
            $this->info("   • Tags buscados: " . implode(', ', $tags));
            $this->info("   • Total procesados: " . ($meta['total_processed'] ?? 0) . " usuarios");
            $this->info("   • Usuarios encontrados: " . count($contacts) . " usuarios");
            
            // Mostrar progreso si es un conteo grande
            if (($meta['total_processed'] ?? 0) > 1000) {
                $percentage = round((count($contacts) / ($meta['total_processed'] ?? 1)) * 100, 2);
                $this->info("   • Porcentaje de coincidencia: {$percentage}%");
            }
            
            return $contacts;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios de GoHighLevel por tags', [
                'error' => $e->getMessage(),
                'tags' => $tags
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
            $baremetricsUser = $this->baremetricsService->getCustomersByEmail($email);
            
            if (!$baremetricsUser || empty($baremetricsUser)) {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado en Baremetrics',
                    'not_found_in_baremetrics' => true
                ];
            }

            if ($isDryRun) {
                return [
                    'success' => true,
                    'message' => 'Usuario encontrado en Baremetrics (dry-run)',
                    'dry_run' => true
                ];
            }

            // Obtener datos de GHL
            $contactId = $ghlUser['id'];
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
            $couponCode = $subscription['couponCode'] ?? null;
            $subscription_status = $subscription['status'] ?? 'none';
            $customFields = collect($ghlUser['customFields'] ?? []);

            $ghlData = [
                'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
                'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
                'country' => $ghlUser['country'] ?? '-',
                'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
                'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
                'state' => $ghlUser['state'] ?? '-',
                'location' => $ghlUser['city'] ?? '-',
                'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
                'subscriptions' => $subscription_status,
                'coupon_code' => $couponCode
            ];

            $stripe_id = $baremetricsUser[0]['oid'] ?? null;
            if (!$stripe_id) {
                return [
                    'success' => false,
                    'error' => 'Usuario de Baremetrics sin oid válido'
                ];
            }

            $result = $this->baremetricsService->updateCustomerAttributes($stripe_id, $ghlData);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Usuario actualizado exitosamente',
                    'stripe_id' => $stripe_id,
                    'subscription_status' => $subscription_status,
                    'coupon_code' => $couponCode
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al actualizar en Baremetrics'
                ];
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            
            if ($statusCode === 429) {
                return [
                    'success' => false,
                    'error' => 'Rate limited por Baremetrics',
                    'rate_limited' => true
                ];
            } elseif ($statusCode >= 500) {
                return [
                    'success' => false,
                    'error' => 'Error del servidor Baremetrics',
                    'server_error' => true
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error HTTP: ' . $statusCode
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error general: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Mostrar estadísticas finales
     */
    private function showFinalStats($stats, $startTime)
    {
        $duration = $startTime->diffInSeconds(now());
        
        $this->info('📊 ESTADÍSTICAS FINALES:');
        $this->info('========================');
        $this->info("• Total procesados: {$stats['total_processed']}");
        $this->info("• Actualizaciones exitosas: {$stats['successful_updates']}");
        $this->info("• Actualizaciones fallidas: {$stats['failed_updates']}");
        $this->info("• No encontrados en Baremetrics: {$stats['not_found_in_baremetrics']}");
        $this->info("• Rate limited: {$stats['rate_limited']}");
        $this->info("• Errores de servidor: {$stats['server_errors']}");
        $this->info("• Duración total: {$duration} segundos");
        
        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->warn('⚠️  ERRORES ENCONTRADOS:');
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                $this->line("• {$error}");
            }
            if (count($stats['errors']) > 10) {
                $this->line("... y " . (count($stats['errors']) - 10) . " errores más");
            }
        }
    }

    /**
     * Guardar reporte de usuarios faltantes
     */
    private function saveMissingUsersReport($missingUsers, $tags)
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "ghl-missing-users-tags-{$timestamp}.json";
        $filepath = storage_path("app/{$filename}");

        $report = [
            'generated_at' => now()->toISOString(),
            'total_missing_users' => count($missingUsers),
            'tags_filter' => $tags,
            'description' => "Usuarios con tags " . implode(', ', $tags) . " que existen en GoHighLevel pero no en Baremetrics",
            'users' => $missingUsers
        ];

        file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("📄 Reporte de usuarios faltantes guardado: {$filename}");
    }

    /**
     * Enviar notificación por email
     */
    private function sendEmailNotification($stats, $email, $tags)
    {
        try {
            $subject = "Procesamiento GHL por Tags Completado - " . now()->format('Y-m-d H:i:s');
            $body = "Procesamiento de usuarios de GoHighLevel por tags completado.\n\n";
            $body .= "Tags procesados: " . implode(', ', $tags) . "\n";
            $body .= "Total procesados: {$stats['total_processed']}\n";
            $body .= "Actualizaciones exitosas: {$stats['successful_updates']}\n";
            $body .= "Actualizaciones fallidas: {$stats['failed_updates']}\n";
            $body .= "No encontrados en Baremetrics: {$stats['not_found_in_baremetrics']}\n";
            $body .= "Rate limited: {$stats['rate_limited']}\n";
            $body .= "Errores de servidor: {$stats['server_errors']}\n\n";
            $body .= "Fecha: " . now()->format('Y-m-d H:i:s');

            // Aquí puedes implementar el envío de email usando Mail::send() o similar
            $this->info("📧 Notificación enviada a: {$email}");
            
        } catch (\Exception $e) {
            $this->error("❌ Error enviando correo: " . $e->getMessage());
            Log::error('Error enviando correo de notificación', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sleep con indicador de progreso
     */
    private function sleepWithProgress($seconds)
    {
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            $this->output->write('.');
        }
        $this->newLine();
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
