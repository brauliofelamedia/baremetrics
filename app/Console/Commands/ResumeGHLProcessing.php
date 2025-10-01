<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ResumeGHLProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:resume-processing 
                           {--from=0 : Usuario desde el cual reanudar (opcional)}
                           {--delay=2 : Delay entre requests en segundos (default: 2)}
                           {--batch-size=50 : Procesar en lotes de N usuarios (default: 50)}
                           {--batch-delay=10 : Delay entre lotes en segundos (default: 10)}
                           {--dry-run : Ejecutar sin hacer cambios reales}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reanuda el procesamiento de usuarios de GoHighLevel desde donde se quedó';

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
        $startFrom = (int) $this->option('from');
        $delay = (float) $this->option('delay');
        $batchSize = (int) $this->option('batch-size');
        $batchDelay = (int) $this->option('batch-delay');
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Reanudando procesamiento de usuarios de GoHighLevel...');
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        $this->info("⏱️  Configuración optimizada para evitar rate limiting:");
        $this->info("   • Delay entre requests: {$delay} segundos");
        $this->info("   • Tamaño de lote: {$batchSize} usuarios");
        $this->info("   • Delay entre lotes: {$batchDelay} segundos");
        $this->info("   • Iniciando desde usuario: {$startFrom}");

        // Inicializar estadísticas
        $stats = [
            'total_processed' => 0,
            'successful_updates' => 0,
            'failed_updates' => 0,
            'rate_limited' => 0,
            'server_errors' => 0,
            'errors' => [],
            'start_time' => now(),
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

            $totalUsers = count($baremetricsUsers);
            $this->info("✅ Se encontraron {$totalUsers} usuarios en Baremetrics");
            $this->info("📊 Índices disponibles: 0 a " . ($totalUsers - 1));

            // Validar índice de inicio
            if ($startFrom > 0) {
                if ($startFrom >= $totalUsers) {
                    $this->error("❌ Error: El índice de inicio ({$startFrom}) es mayor o igual al total de usuarios ({$totalUsers})");
                    $this->info("💡 Los índices válidos van de 0 a " . ($totalUsers - 1));
                    $this->info("💡 Usa --from=0 para procesar todos los usuarios");
                    return 1;
                }
                
                $baremetricsUsers = array_slice($baremetricsUsers, $startFrom);
                $remainingUsers = count($baremetricsUsers);
                $this->info("🔢 Reanudando desde índice {$startFrom} (quedan {$remainingUsers} usuarios)");
                
                if ($remainingUsers === 0) {
                    $this->warn("⚠️  No hay usuarios para procesar desde el índice {$startFrom}");
                    return 0;
                }
            } else {
                $this->info("🔄 Procesando todos los usuarios desde el inicio");
            }

            // Validar estructura de datos
            $this->info("🔍 Validando estructura de datos...");
            $validUsers = [];
            $invalidUsers = 0;
            
            foreach ($baremetricsUsers as $user) {
                if (isset($user['oid']) && isset($user['email'])) {
                    $validUsers[] = $user;
                } else {
                    $invalidUsers++;
                    if ($invalidUsers <= 3) { // Mostrar solo los primeros 3 errores
                        $this->warn("⚠️  Usuario inválido encontrado: " . json_encode($user));
                    }
                }
            }
            
            if ($invalidUsers > 0) {
                $this->warn("⚠️  Se encontraron {$invalidUsers} usuarios con estructura inválida");
                $baremetricsUsers = $validUsers;
                $this->info("✅ Continuando con " . count($validUsers) . " usuarios válidos");
            }

            $stats['total_processed'] = count($baremetricsUsers);

            // Crear barra de progreso
            $progressBar = $this->output->createProgressBar($stats['total_processed']);
            $progressBar->start();

            // Procesar en lotes más pequeños para evitar rate limiting
            $batches = array_chunk($baremetricsUsers, $batchSize);
            $totalBatches = count($batches);
            
            $this->newLine();
            $this->info("📦 Procesando {$totalBatches} lotes de {$batchSize} usuarios cada uno");

            foreach ($batches as $batchIndex => $batch) {
                $batchNumber = $batchIndex + 1;
                $this->info("🔄 Procesando lote {$batchNumber}/{$totalBatches}...");
                
                foreach ($batch as $userIndex => $user) {
                    $email = $user['email'] ?? null;
                    $oid = $user['oid'] ?? null;
                    
                    if (!$email) {
                        $stats['errors'][] = "Usuario sin email (ID: " . ($oid ?? 'desconocido') . ")";
                        $progressBar->advance();
                        continue;
                    }
                    
                    if (!$oid) {
                        $stats['errors'][] = "Usuario sin OID (email: " . ($email ?? 'desconocido') . ")";
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

            // Mostrar recomendaciones si hay rate limiting
            if ($stats['rate_limited'] > 0) {
                $this->newLine();
                $this->warn('⚠️  Se detectaron errores de rate limiting. Recomendaciones:');
                $this->line('   • Aumentar el delay entre requests: --delay=3');
                $this->line('   • Reducir el tamaño de lote: --batch-size=25');
                $this->line('   • Aumentar el delay entre lotes: --batch-delay=15');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            Log::error('Error en procesamiento masivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
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
     * Obtener todos los usuarios de Baremetrics
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

        // Obtener clientes de cada fuente
        foreach ($sourceIds as $sourceId) {
            $page = 1;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomers($sourceId, $page);
                
                if (!$response) {
                    break;
                }

                $customers = [];
                if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
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
            // Validar que el usuario tenga OID
            if (!isset($baremetricsUser['oid']) || empty($baremetricsUser['oid'])) {
                return [
                    'success' => false,
                    'error' => 'Usuario sin OID válido'
                ];
            }
            
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
     * Muestra resumen de estadísticas
     */
    private function displaySummary($stats)
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE PROCESAMIENTO');
        $this->info('============================');
        $this->line("⏱️  Duración: {$stats['duration']} minutos");
        $this->line("📈 Total procesados: {$stats['total_processed']}");
        $this->line("✅ Actualizaciones exitosas: {$stats['successful_updates']}");
        $this->line("❌ Actualizaciones fallidas: {$stats['failed_updates']}");
        
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
    }
}
