<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class ProcessAllGHLUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:process-all-users 
                           {--limit= : Límite de usuarios a procesar (opcional)}
                           {--dry-run : Ejecutar sin hacer cambios reales}
                           {--email= : Correo para notificaciones (opcional)}
                           {--delay=1 : Delay entre requests en segundos (default: 1)}
                           {--batch-size=100 : Procesar en lotes de N usuarios (default: 100)}
                           {--batch-delay=5 : Delay entre lotes en segundos (default: 5)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa todos los usuarios de GoHighLevel y actualiza sus campos personalizados en Baremetrics';

    protected $ghlService;
    protected $baremetricsService;
    protected $stripeService;

    public function __construct(
        GoHighLevelService $ghlService,
        BaremetricsService $baremetricsService,
        StripeService $stripeService
    ) {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
        $this->stripeService = $stripeService;
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

        $this->info('🚀 Iniciando procesamiento de usuarios de GoHighLevel...');
        
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

        // Inicializar estadísticas
        $stats = [
            'total_processed' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'not_found_in_ghl' => 0,
            'not_found_in_stripe' => 0,
            'errors' => [],
            'start_time' => $startTime,
            'end_time' => null,
            'is_dry_run' => $isDryRun
        ];

        try {
            // Obtener todos los usuarios de Baremetrics
            $this->info('📥 Obteniendo usuarios de Baremetrics...');
            $baremetricsUsers = $this->getAllBaremetricsUsers();
            
            if (empty($baremetricsUsers)) {
                $this->error('❌ No se encontraron usuarios en Baremetrics');
                return 1;
            }

            $this->info("✅ Se encontraron " . count($baremetricsUsers) . " usuarios en Baremetrics");

            // Aplicar límite si se especifica
            if ($limit) {
                $baremetricsUsers = array_slice($baremetricsUsers, 0, (int)$limit);
                $this->info("🔢 Procesando solo los primeros {$limit} usuarios");
            }

            $stats['total_processed'] = count($baremetricsUsers);

            // Crear barra de progreso
            $progressBar = $this->output->createProgressBar($stats['total_processed']);
            $progressBar->start();

            // Procesar en lotes
            $batches = array_chunk($baremetricsUsers, $batchSize);
            $totalBatches = count($batches);
            
            $this->newLine();
            $this->info("📦 Procesando {$totalBatches} lotes de {$batchSize} usuarios cada uno");

            foreach ($batches as $batchIndex => $batch) {
                $batchNumber = $batchIndex + 1;
                $this->info("🔄 Procesando lote {$batchNumber}/{$totalBatches}...");
                
                foreach ($batch as $userIndex => $user) {
                    $email = $user['email'] ?? null;
                    
                    if (!$email) {
                        $stats['errors'][] = "Usuario sin email (ID: {$user['oid']})";
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
                        }
                    } catch (\Exception $e) {
                        $stats['failed_updates']++;
                        $stats['errors'][] = "Excepción procesando {$email}: " . $e->getMessage();
                        Log::error('Error procesando usuario', [
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

            // Enviar correo de notificación
            if ($notificationEmail) {
                $this->sendNotificationEmail($stats, $notificationEmail);
            }

            $this->info('✅ Procesamiento completado exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error crítico: ' . $e->getMessage());
            Log::error('Error crítico en procesamiento de usuarios GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Obtiene todos los usuarios de Baremetrics
     */
    private function getAllBaremetricsUsers()
    {
        $allUsers = [];
        
        // Obtener fuentes de Stripe
        $sources = $this->baremetricsService->getSources();
        
        if (!$sources) {
            throw new \Exception('No se pudieron obtener las fuentes de Baremetrics');
        }

        // Normalizar respuesta de fuentes
        $sourcesNew = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesNew = $sources['sources'];
        } elseif (is_array($sources)) {
            $sourcesNew = $sources;
        }

        // Filtrar solo fuentes de Stripe
        $stripeSources = array_values(array_filter($sourcesNew, function ($source) {
            return isset($source['provider']) && $source['provider'] === 'stripe';
        }));

        $sourceIds = array_values(array_filter(array_column($stripeSources, 'id'), function ($id) {
            return !empty($id);
        }));

        if (empty($sourceIds)) {
            throw new \Exception('No se encontraron fuentes de Stripe en Baremetrics');
        }

        // Obtener usuarios de cada fuente
        foreach ($sourceIds as $sourceId) {
            $page = 0;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomersAll($sourceId, $page);
                
                if (!$response) {
                    $hasMore = false;
                    continue;
                }

                $customers = [];
                $pagination = [];
                
                if (isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }

                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                if (!empty($customers)) {
                    $allUsers = array_merge($allUsers, $customers);
                }

                $page++;
                usleep(100000); // Pequeña pausa entre requests
            }
        }

        return $allUsers;
    }

    /**
     * Procesa un usuario individual
     */
    private function processUser($email, $baremetricsUser, $isDryRun = false)
    {
        try {
            // Buscar usuario en GoHighLevel con búsqueda mejorada
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con contains
            if (empty($ghlCustomer['contacts'])) {
                $ghlCustomer = $this->ghlService->getContacts($email);
            }
            
            if (empty($ghlCustomer['contacts'])) {
                return [
                    'success' => false,
                    'error' => 'Usuario no encontrado en GoHighLevel'
                ];
            }

            $contact = $ghlCustomer['contacts'][0];
            $contactId = $contact['id'];

            // Obtener campos personalizados
            $customFields = collect($contact['customFields'] ?? []);
            
            // Obtener datos de suscripción más reciente
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
            $couponCode = $subscription['couponCode'] ?? null;
            $subscriptionStatus = $subscription['status'] ?? 'none';
            
            // Log para debugging
            Log::debug('Datos de suscripción obtenidos', [
                'email' => $email,
                'contact_id' => $contactId,
                'subscription_id' => $subscription['id'] ?? 'N/A',
                'status' => $subscriptionStatus,
                'coupon_code' => $couponCode,
                'created_at' => $subscription['createdAt'] ?? 'N/A'
            ]);

            // Preparar datos para Baremetrics
            $ghlData = [
                'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
                'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
                'country' => $contact['country'] ?? '-',
                'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
                'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
                'state' => $contact['state'] ?? '-',
                'location' => $contact['city'] ?? '-',
                'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
                'subscriptions' => $subscriptionStatus,
                'coupon_code' => $couponCode
            ];

            if ($isDryRun) {
                Log::info('DRY-RUN: Datos que se actualizarían', [
                    'email' => $email,
                    'customer_oid' => $baremetricsUser['oid'],
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
                    Log::info('Usuario actualizado exitosamente', [
                        'email' => $email,
                        'customer_oid' => $baremetricsUser['oid']
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
            Log::error('Error procesando usuario', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Muestra el resumen de estadísticas
     */
    private function displaySummary($stats)
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE PROCESAMIENTO');
        $this->info('========================');
        
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total procesados', $stats['total_processed']],
                ['Actualizaciones exitosas', $stats['successful_updates']],
                ['Actualizaciones fallidas', $stats['failed_updates']],
                ['Tasa de éxito', $stats['total_processed'] > 0 ? 
                    round(($stats['successful_updates'] / $stats['total_processed']) * 100, 2) . '%' : '0%'],
                ['Duración', $stats['duration'] . ' minutos'],
                ['Modo', $stats['is_dry_run'] ? 'DRY-RUN' : 'REAL']
            ]
        );

        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->warn('⚠️  ERRORES ENCONTRADOS:');
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                $this->line("• {$error}");
            }
            
            if (count($stats['errors']) > 10) {
                $this->line("• ... y " . (count($stats['errors']) - 10) . " errores más");
            }
        }
    }

    /**
     * Envía correo de notificación con el estado
     */
    private function sendNotificationEmail($stats, $email)
    {
        try {
            $subject = 'Reporte de Procesamiento de Usuarios GHL - ' . now()->format('Y-m-d H:i:s');
            
            $data = [
                'stats' => $stats,
                'subject' => $subject,
                'is_dry_run' => $stats['is_dry_run']
            ];

            Mail::send('emails.ghl-processing-report', $data, function ($message) use ($email, $subject) {
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
}
