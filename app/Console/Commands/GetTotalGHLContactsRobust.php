<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class GetTotalGHLContactsRobust extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:get-total-contacts-robust 
                           {--limit= : Límite máximo de contactos a procesar}
                           {--timeout=30 : Timeout en segundos por página}
                           {--max-pages=50 : Máximo número de páginas a procesar}
                           {--delay=1 : Delay en segundos entre páginas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtener el total real de contactos en GoHighLevel con manejo robusto de errores';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $timeout = (int) $this->option('timeout');
        $maxPages = (int) $this->option('max-pages');
        $delay = (int) $this->option('delay');

        $this->info('🔍 OBTENIENDO TOTAL DE CONTACTOS EN GOHIGHLEVEL (MODO ROBUSTO)');
        $this->info('==============================================================');
        $this->newLine();

        try {
            $startTime = microtime(true);
            $totalContacts = 0;
            $page = 1;
            $hasMore = true;
            $contactsWithEmail = 0;
            $contactsWithTags = 0;
            $errors = 0;
            $consecutiveErrors = 0;

            $this->info('📊 Iniciando conteo de contactos con manejo robusto de errores...');
            $this->line("⚙️  Configuración:");
            $this->line("  • Timeout por página: {$timeout}s");
            $this->line("  • Máximo de páginas: {$maxPages}");
            $this->line("  • Delay entre páginas: {$delay}s");
            if ($limit) {
                $this->line("  • Límite de contactos: {$limit}");
            }
            $this->newLine();

            // Usar pageLimit más pequeño para mayor estabilidad
            $pageLimit = 100; // Reducido para mayor estabilidad

            while ($hasMore && $page <= $maxPages) {
                $this->line("📄 Procesando página {$page}...");
                
                try {
                    // Usar timeout personalizado
                    $pageStartTime = microtime(true);
                    
                    $response = $this->ghlService->getContacts('', $page, $pageLimit);
                    
                    $pageEndTime = microtime(true);
                    $pageDuration = round(($pageEndTime - $pageStartTime) * 1000, 2);
                    
                    if (!$response) {
                        $this->warn("⚠️  No se obtuvo respuesta de la API en la página {$page}");
                        $errors++;
                        $consecutiveErrors++;
                        
                        if ($consecutiveErrors >= 3) {
                            $this->error("❌ Demasiados errores consecutivos. Deteniendo proceso.");
                            break;
                        }
                        
                        $page++;
                        sleep($delay);
                        continue;
                    }

                    if (empty($response['contacts'])) {
                        $this->warn("⚠️  No se obtuvieron contactos en la página {$page}");
                        $hasMore = false;
                        break;
                    }

                    $contacts = $response['contacts'];
                    $pageTotal = count($contacts);
                    $totalContacts += $pageTotal;

                    // Estadísticas
                    foreach ($contacts as $contact) {
                        if (!empty($contact['email'])) {
                            $contactsWithEmail++;
                        }
                        
                        if (!empty($contact['tags'])) {
                            $contactsWithTags++;
                        }
                    }

                    $this->line("  ✅ {$pageTotal} contactos obtenidos (Total: {$totalContacts}) en {$pageDuration}ms");
                    
                    // Resetear contador de errores consecutivos
                    $consecutiveErrors = 0;

                    // Verificar paginación
                    if (isset($response['meta']['pagination'])) {
                        $pagination = $response['meta']['pagination'];
                        $hasMore = $pagination['has_more'] ?? false;
                    } else {
                        // Si no hay información de paginación, continuar si obtuvimos contactos
                        $hasMore = $pageTotal > 0;
                    }

                    // Verificar límite si se especifica
                    if ($limit && $totalContacts >= $limit) {
                        $this->warn("⚠️  Límite alcanzado: {$limit} contactos");
                        break;
                    }

                    // Delay entre páginas para no sobrecargar la API
                    if ($delay > 0) {
                        $this->line("  ⏳ Esperando {$delay}s antes de la siguiente página...");
                        sleep($delay);
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $consecutiveErrors++;
                    
                    $this->error("❌ Error en página {$page}: " . $e->getMessage());
                    
                    if ($consecutiveErrors >= 3) {
                        $this->error("❌ Demasiados errores consecutivos. Deteniendo proceso.");
                        break;
                    }
                    
                    $this->line("  🔄 Reintentando página {$page} en 5 segundos...");
                    sleep(5);
                    continue; // No incrementar $page para reintentar
                }

                $page++;
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $this->newLine();
            $this->info('📊 RESULTADOS FINALES:');
            $this->info('====================');
            $this->line("🎯 Total de contactos: {$totalContacts}");
            $this->line("📧 Contactos con email: {$contactsWithEmail}");
            $this->line("🏷️  Contactos con tags: {$contactsWithTags}");
            $this->line("⏱️  Tiempo total: {$duration} segundos");
            $this->line("📄 Páginas procesadas: " . ($page - 1));
            $this->line("❌ Errores encontrados: {$errors}");
            
            if ($totalContacts > 0) {
                $speed = round($totalContacts / $duration, 2);
                $this->line("⚡ Velocidad: {$speed} contactos/segundo");
                
                $emailPercentage = round(($contactsWithEmail / $totalContacts) * 100, 2);
                $tagsPercentage = round(($contactsWithTags / $totalContacts) * 100, 2);
                $this->line("📊 % con email: {$emailPercentage}%");
                $this->line("📊 % con tags: {$tagsPercentage}%");
            }

            if ($page > $maxPages) {
                $this->warn("⚠️  Se alcanzó el límite de páginas ({$maxPages})");
                $this->line("💡 Hay más contactos disponibles. Ejecuta con --max-pages=X para procesar más páginas");
            }

            $this->info('✅ Conteo completado exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error obteniendo total de contactos GHL (modo robusto)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
