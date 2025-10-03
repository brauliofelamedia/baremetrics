<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CountGHLUsersWithFilters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:count-users 
                           {--active-only : Solo usuarios activos (default: true)}
                           {--with-subscription : Solo usuarios con suscripción activa (default: true)}
                           {--no-filters : Desactivar todos los filtros (contar todos los usuarios)}
                           {--limit=1000 : Límite de usuarios a procesar para el conteo (default: 1000)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Contar usuarios de GoHighLevel con filtros específicos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ghlService = app(GoHighLevelService::class);
        
        $activeOnly = $this->option('active-only');
        $withSubscription = $this->option('with-subscription');
        $noFilters = $this->option('no-filters');
        $limit = (int) $this->option('limit');

        $this->info('🔍 CONTEO DE USUARIOS DE GOHIGHLEVEL CON FILTROS');
        $this->info('================================================');
        
        $this->info("🔧 Configuración:");
        $this->info("   • Solo usuarios activos: " . ($activeOnly ? 'SÍ' : 'NO'));
        $this->info("   • Solo con suscripción activa: " . ($withSubscription ? 'SÍ' : 'NO'));
        $this->info("   • Sin filtros: " . ($noFilters ? 'SÍ' : 'NO'));
        $this->info("   • Límite de procesamiento: {$limit} usuarios");
        $this->newLine();

        try {
            $stats = $this->countUsersWithFilters($ghlService, $limit, $activeOnly, $withSubscription, $noFilters);
            
            $this->info('📊 RESULTADOS DEL CONTEO:');
            $this->info('=========================');
            $this->info("• Total usuarios procesados: {$stats['total_processed']}");
            $this->info("• Usuarios que pasaron filtros: {$stats['filtered_count']}");
            $this->info("• Porcentaje filtrado: " . round(($stats['filtered_count'] / $stats['total_processed']) * 100, 2) . "%");
            $this->newLine();
            
            if ($stats['total_processed'] >= $limit) {
                $this->warn("⚠️  Se alcanzó el límite de {$limit} usuarios. Puede haber más usuarios.");
                $this->info("💡 Para un conteo completo, ejecuta sin --limit o con un límite mayor.");
            }
            
            $this->info('✅ Conteo completado exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error en conteo de usuarios GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Contar usuarios con filtros aplicados
     */
    private function countUsersWithFilters($ghlService, $limit, $activeOnly, $withSubscription, $noFilters)
    {
        $totalProcessed = 0;
        $filteredCount = 0;
        $page = 1;
        $hasMore = true;

        $this->info("🔄 Procesando usuarios...");

        while ($hasMore && $totalProcessed < $limit) {
            $response = $ghlService->getContacts('', $page);
            
            if (!$response || empty($response['contacts'])) {
                break;
            }

            $contacts = $response['contacts'];
            $batchSize = count($contacts);
            $totalProcessed += $batchSize;

            // Aplicar filtros si no se desactivan
            if (!$noFilters) {
                foreach ($contacts as $contact) {
                    $shouldInclude = true;
                    
                    // Filtro 1: Solo usuarios activos
                    if ($activeOnly && isset($contact['status']) && $contact['status'] !== 'active') {
                        $shouldInclude = false;
                    }
                    
                    // Filtro 2: Solo usuarios con suscripción activa
                    if ($shouldInclude && $withSubscription) {
                        try {
                            $subscription = $ghlService->getSubscriptionStatusByContact($contact['id']);
                            if (!$subscription || ($subscription['status'] ?? '') !== 'active') {
                                $shouldInclude = false;
                            }
                        } catch (\Exception $e) {
                            // Si hay error obteniendo suscripción, excluir el usuario
                            $shouldInclude = false;
                        }
                    }
                    
                    if ($shouldInclude) {
                        $filteredCount++;
                    }
                }
            } else {
                $filteredCount += $batchSize;
            }

            // Verificar paginación
            if (isset($response['meta']['pagination'])) {
                $pagination = $response['meta']['pagination'];
                $hasMore = $pagination['has_more'] ?? false;
            } else {
                $hasMore = false;
            }

            $page++;

            // Mostrar progreso cada 100 usuarios
            if ($totalProcessed % 100 === 0) {
                $this->info("📊 Procesados: {$totalProcessed} usuarios, Filtrados: {$filteredCount} usuarios");
            }

            // Pequeña pausa entre requests
            usleep(100000); // 0.1 segundos
        }

        return [
            'total_processed' => $totalProcessed,
            'filtered_count' => $filteredCount
        ];
    }
}
