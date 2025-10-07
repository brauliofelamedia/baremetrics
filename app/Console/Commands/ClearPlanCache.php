<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearPlanCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baremetrics:clear-plan-cache {--source-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpiar cache de planes de Baremetrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sourceId = $this->option('source-id');
        
        $this->info("🧹 Limpiando cache de planes de Baremetrics");
        $this->line("==========================================");

        if ($sourceId) {
            $this->info("\n🎯 Limpiando cache para Source ID específico: {$sourceId}");
            
            // Limpiar cache específico para un source ID
            $pattern = "baremetrics_plan_{$sourceId}_*";
            $this->clearCacheByPattern($pattern);
            
        } else {
            $this->info("\n🌐 Limpiando todo el cache de planes de Baremetrics");
            
            // Limpiar todo el cache de planes
            $pattern = "baremetrics_plan_*";
            $this->clearCacheByPattern($pattern);
        }

        $this->info("\n✅ ¡Cache limpiado exitosamente!");
        
        return 0;
    }

    /**
     * Limpiar cache por patrón
     */
    private function clearCacheByPattern(string $pattern)
    {
        $driver = Cache::getDefaultDriver();
        
        if ($driver === 'redis') {
            // Para Redis, usar SCAN para encontrar keys
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                $redis->del($keys);
                $this->line("   • Eliminadas " . count($keys) . " entradas de cache");
                
                foreach ($keys as $key) {
                    $this->line("     - {$key}");
                }
            } else {
                $this->line("   • No se encontraron entradas de cache con el patrón: {$pattern}");
            }
            
        } elseif ($driver === 'file') {
            // Para file cache, buscar archivos
            $cachePath = storage_path('framework/cache/data');
            $files = glob($cachePath . '/*');
            $deletedCount = 0;
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (strpos($content, 'baremetrics_plan_') !== false) {
                    unlink($file);
                    $deletedCount++;
                }
            }
            
            if ($deletedCount > 0) {
                $this->line("   • Eliminados {$deletedCount} archivos de cache");
            } else {
                $this->line("   • No se encontraron archivos de cache con planes");
            }
            
        } else {
            $this->warn("   ⚠️ Driver de cache '{$driver}' no soportado para limpieza por patrón");
            $this->line("   • Usa 'php artisan cache:clear' para limpiar todo el cache");
        }
    }
}
