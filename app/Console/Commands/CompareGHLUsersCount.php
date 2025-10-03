<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CompareGHLUsersCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:compare-users-count 
                           {--limit=1000 : Límite de usuarios a procesar para la comparación}
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--method=optimized : Método a usar (optimized o standard)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comparar conteo total de usuarios vs usuarios filtrados por tags';

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
        $limit = (int) $this->option('limit');
        $tagsString = $this->option('tags');
        $tags = array_map('trim', explode(',', $tagsString));
        $method = $this->option('method');

        $this->info('🔄 COMPARACIÓN DE CONTEOS DE USUARIOS');
        $this->info('=====================================');
        
        $this->info("📊 Límite de prueba: {$limit} usuarios");
        $this->info("🏷️  Tags a buscar: " . implode(', ', $tags));
        $this->info("🔧 Método: {$method}");
        $this->newLine();

        try {
            // Paso 1: Obtener total de usuarios sin filtros
            $this->info('📥 PASO 1: Obteniendo total de usuarios sin filtros...');
            $totalUsersResult = $this->getTotalUsers($limit, $method);
            
            // Paso 2: Obtener usuarios filtrados por tags
            $this->info('🔍 PASO 2: Obteniendo usuarios filtrados por tags...');
            $filteredUsersResult = $this->getFilteredUsers($tags, $limit, $method);
            
            // Paso 3: Comparar resultados
            $this->info('📊 PASO 3: Comparando resultados...');
            $this->compareResults($totalUsersResult, $filteredUsersResult, $tags);
            
            $this->info('✅ Comparación completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la comparación: " . $e->getMessage());
            Log::error('Error en comparación de conteos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Obtener total de usuarios sin filtros
     */
    private function getTotalUsers($limit, $method)
    {
        $allContacts = [];
        $page = 1;
        $hasMore = true;
        $processedCount = 0;
        $startTime = now();

        $pageLimit = $method === 'optimized' ? 1000 : 100;

        while ($hasMore && $processedCount < $limit) {
            try {
                $response = $this->ghlService->getContacts('', $page, $pageLimit);
                
                if (!$response || empty($response['contacts'])) {
                    break;
                }

                $contacts = $response['contacts'];
                $processedCount += count($contacts);
                
                if (!empty($contacts)) {
                    $allContacts = array_merge($allContacts, $contacts);
                }

                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;
                usleep(50000); // 0.05 segundos

            } catch (\Exception $e) {
                $this->error("❌ Error obteniendo total de usuarios: " . $e->getMessage());
                break;
            }
        }

        $endTime = now();
        $duration = $startTime->diffInSeconds($endTime);

        return [
            'total_processed' => $processedCount,
            'contacts_found' => count($allContacts),
            'duration' => $duration,
            'has_more' => $hasMore,
            'contacts' => $allContacts
        ];
    }

    /**
     * Obtener usuarios filtrados por tags
     */
    private function getFilteredUsers($tags, $limit, $method)
    {
        $startTime = now();
        
        if ($method === 'optimized') {
            $response = $this->ghlService->getContactsByTagsOptimized($tags, $limit);
        } else {
            $response = $this->ghlService->getContactsByTagsAlternative($tags, $limit);
        }
        
        $endTime = now();
        $duration = $startTime->diffInSeconds($endTime);

        return [
            'total_processed' => $response['meta']['total_processed'] ?? 0,
            'contacts_found' => count($response['contacts'] ?? []),
            'duration' => $duration,
            'has_more' => false, // El método alternativo procesa todo
            'contacts' => $response['contacts'] ?? [],
            'meta' => $response['meta'] ?? []
        ];
    }

    /**
     * Comparar resultados
     */
    private function compareResults($totalResult, $filteredResult, $tags)
    {
        $this->newLine();
        $this->info('📊 RESULTADOS DE LA COMPARACIÓN:');
        $this->info('================================');
        
        // Mostrar resultados del total
        $this->info('📥 TOTAL DE USUARIOS (SIN FILTROS):');
        $this->info("   • Total procesados: {$totalResult['total_processed']}");
        $this->info("   • Contactos obtenidos: {$totalResult['contacts_found']}");
        $this->info("   • Duración: {$totalResult['duration']} segundos");
        $this->info("   • Hay más páginas: " . ($totalResult['has_more'] ? 'SÍ' : 'NO'));
        
        // Mostrar resultados filtrados
        $this->newLine();
        $this->info('🔍 USUARIOS FILTRADOS POR TAGS:');
        $this->info("   • Tags buscados: " . implode(', ', $tags));
        $this->info("   • Total procesados: {$filteredResult['total_processed']}");
        $this->info("   • Contactos encontrados: {$filteredResult['contacts_found']}");
        $this->info("   • Duración: {$filteredResult['duration']} segundos");
        
        if (isset($filteredResult['meta']['efficiency_percentage'])) {
            $this->info("   • Eficiencia: {$filteredResult['meta']['efficiency_percentage']}%");
        }

        // Análisis de la comparación
        $this->newLine();
        $this->info('🔍 ANÁLISIS DE LA COMPARACIÓN:');
        $this->info('==============================');
        
        if ($totalResult['total_processed'] > 0 && $filteredResult['total_processed'] > 0) {
            $ratio = round($filteredResult['total_processed'] / $totalResult['total_processed'], 4);
            $percentage = round($ratio * 100, 2);
            
            $this->info("• Ratio filtrado/total: {$ratio}");
            $this->info("• Porcentaje filtrado: {$percentage}%");
            
            if ($percentage < 1) {
                $this->warn('⚠️  ADVERTENCIA: Muy pocos usuarios tienen los tags especificados');
                $this->line('   Posibles causas:');
                $this->line('   • Los tags no existen o son poco comunes');
                $this->line('   • Los tags tienen diferente escritura');
                $this->line('   • Los usuarios están en una ubicación diferente');
            } elseif ($percentage > 50) {
                $this->warn('⚠️  ADVERTENCIA: Muchos usuarios tienen los tags especificados');
                $this->line('   Esto puede indicar que los tags son muy comunes');
            } else {
                $this->info('✅ El porcentaje de usuarios con tags parece razonable');
            }
        }

        // Comparar velocidades
        if ($totalResult['duration'] > 0 && $filteredResult['duration'] > 0) {
            $totalSpeed = round($totalResult['total_processed'] / $totalResult['duration'], 2);
            $filteredSpeed = round($filteredResult['total_processed'] / $filteredResult['duration'], 2);
            
            $this->newLine();
            $this->info('⚡ COMPARACIÓN DE VELOCIDADES:');
            $this->info("   • Velocidad total: {$totalSpeed} usuarios/segundo");
            $this->info("   • Velocidad filtrado: {$filteredSpeed} usuarios/segundo");
            
            if ($filteredSpeed < $totalSpeed * 0.5) {
                $this->warn('⚠️  ADVERTENCIA: El filtrado es significativamente más lento');
                $this->line('   Esto puede indicar un problema de rendimiento en el filtrado');
            }
        }

        // Verificar consistencia
        $this->newLine();
        $this->info('🔍 VERIFICACIÓN DE CONSISTENCIA:');
        $this->info('================================');
        
        if ($totalResult['has_more'] && !$filteredResult['has_more']) {
            $this->warn('⚠️  INCONSISTENCIA: El total tiene más páginas pero el filtrado no');
            $this->line('   Esto puede indicar que el filtrado se detuvo prematuramente');
        }
        
        if ($totalResult['total_processed'] < $filteredResult['total_processed']) {
            $this->error('❌ ERROR: El filtrado procesó más usuarios que el total');
            $this->line('   Esto no debería ser posible - hay un error en la lógica');
        }

        // Recomendaciones
        $this->newLine();
        $this->info('💡 RECOMENDACIONES:');
        $this->info('===================');
        
        if ($filteredResult['contacts_found'] < 10) {
            $this->line('• Ejecutar diagnóstico de tags: php artisan ghl:diagnose-tags --limit=1000 --show-tags');
            $this->line('• Verificar que los tags existen en GoHighLevel');
            $this->line('• Probar con límite mayor: --limit=10000');
        } else {
            $this->line('• Los resultados parecen consistentes');
            $this->line('• Considerar usar el método optimizado para el procesamiento completo');
        }
    }
}
