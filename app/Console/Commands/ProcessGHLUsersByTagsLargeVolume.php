<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class ProcessGHLUsersByTagsLargeVolume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:process-by-tags-large 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--limit= : Límite de usuarios a procesar (opcional)}
                           {--delay=1 : Delay entre requests en segundos (default: 1)}
                           {--batch-size=100 : Procesar en lotes de N usuarios (default: 100)}
                           {--batch-delay=5 : Delay entre lotes en segundos (default: 5)}
                           {--dry-run : Ejecutar sin hacer cambios reales}
                           {--count-only : Solo contar usuarios, no procesar}
                           {--progress-interval=1000 : Mostrar progreso cada N usuarios (default: 1000)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesar usuarios de GoHighLevel por tags optimizado para grandes volúmenes (100,000+ usuarios)';

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
        $startTime = now();
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $delay = (float) $this->option('delay');
        $batchSize = (int) $this->option('batch-size');
        $batchDelay = (int) $this->option('batch-delay');
        $countOnly = $this->option('count-only');
        $progressInterval = (int) $this->option('progress-interval');
        
        // Parsear tags
        $tagsString = $this->option('tags');
        $tags = array_map('trim', explode(',', $tagsString));

        $this->info('🚀 PROCESAMIENTO DE GRANDES VOLÚMENES POR TAGS');
        $this->info('==============================================');
        
        $this->info("🏷️  Tags configurados: " . implode(', ', $tags));
        $this->info("📊 Optimizado para: 100,000+ usuarios");
        
        if ($isDryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales');
        }

        if ($countOnly) {
            $this->info('📊 MODO CONTEO: Solo se contarán usuarios, no se procesarán');
        }

        $this->info("⏱️  Configuración optimizada:");
        $this->info("   • Delay entre requests: {$delay} segundos");
        $this->info("   • Tamaño de lote: {$batchSize} usuarios");
        $this->info("   • Delay entre lotes: {$batchDelay} segundos");
        $this->info("   • Progreso cada: {$progressInterval} usuarios");

        try {
            // Obtener usuarios de GoHighLevel por tags
            $this->info('📥 Obteniendo usuarios de GoHighLevel por tags (método optimizado)...');
            $ghlUsers = $this->getAllGHLUsersByTagsOptimized($tags, $limit, $progressInterval);
            
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

            // Mostrar estadísticas de rendimiento
            $duration = $startTime->diffInSeconds(now());
            $this->info("⏱️  Tiempo de búsqueda: {$duration} segundos");
            $this->info("📈 Velocidad: " . round($totalUsers / max($duration, 1), 2) . " usuarios/segundo");

            $this->info('✅ Búsqueda completada exitosamente');
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el procesamiento: " . $e->getMessage());
            Log::error('Error en procesamiento GHL por tags (grandes volúmenes)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }
    }

    /**
     * Obtener todos los usuarios de GoHighLevel por tags (método optimizado)
     */
    private function getAllGHLUsersByTagsOptimized($tags, $limit = null, $progressInterval = 1000)
    {
        try {
            $this->info("🔍 Buscando usuarios con tags: " . implode(', ', $tags));
            $this->info("📊 Usando método optimizado para grandes volúmenes...");

            // Usar el método optimizado para grandes volúmenes
            $response = $this->ghlService->getContactsByTagsOptimized($tags, $limit);
            
            if (!$response || empty($response['contacts'])) {
                return [];
            }

            $contacts = $response['contacts'];
            $meta = $response['meta'] ?? [];

            $this->info("📊 RESUMEN DE BÚSQUEDA OPTIMIZADA:");
            $this->info("   • Tags buscados: " . implode(', ', $tags));
            $this->info("   • Total procesados: " . ($meta['total_processed'] ?? 0) . " usuarios");
            $this->info("   • Usuarios encontrados: " . count($contacts) . " usuarios");
            
            // Mostrar eficiencia
            if (isset($meta['efficiency_percentage'])) {
                $this->info("   • Eficiencia: {$meta['efficiency_percentage']}%");
            }
            
            // Mostrar velocidad estimada
            $estimatedTotal = 100000; // Estimación conservadora
            if (($meta['total_processed'] ?? 0) > 0) {
                $estimatedTime = round(($estimatedTotal / ($meta['total_processed'] ?? 1)) * 10, 2);
                $this->info("   • Tiempo estimado para 100K usuarios: {$estimatedTime} minutos");
            }
            
            return $contacts;
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo usuarios de GoHighLevel por tags (optimizado)', [
                'error' => $e->getMessage(),
                'tags' => $tags
            ]);
            throw $e;
        }
    }
}
