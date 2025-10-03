<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class DiagnoseGHLPagination extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:diagnose-pagination 
                           {--pages=5 : Número de páginas a probar}
                           {--limit=500 : Límite por página}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosticar la paginación de contactos en GoHighLevel';

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
        $maxPages = $this->option('pages');
        $pageLimit = $this->option('limit');

        $this->info('🔍 DIAGNÓSTICO DE PAGINACIÓN EN GOHIGHLEVEL');
        $this->info('============================================');
        $this->newLine();

        try {
            $totalContacts = 0;
            $page = 1;
            $hasMore = true;

            $this->info("📊 Probando paginación con límite {$pageLimit} por página...");
            $this->newLine();

            while ($hasMore && $page <= $maxPages) {
                $this->line("📄 === PÁGINA {$page} ===");
                
                $startTime = microtime(true);
                $response = $this->ghlService->getContacts('', $page, $pageLimit);
                $endTime = microtime(true);
                
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                if (!$response) {
                    $this->error("❌ No se obtuvo respuesta de la API");
                    break;
                }

                if (empty($response['contacts'])) {
                    $this->warn("⚠️  No se obtuvieron contactos en la página {$page}");
                    break;
                }

                $contacts = $response['contacts'];
                $pageTotal = count($contacts);
                $totalContacts += $pageTotal;

                $this->line("✅ Contactos obtenidos: {$pageTotal}");
                $this->line("📊 Total acumulado: {$totalContacts}");
                $this->line("⏱️  Tiempo: {$duration}ms");

                // Mostrar información de paginación
                if (isset($response['meta'])) {
                    $this->line("📋 Meta información:");
                    $meta = $response['meta'];
                    
                    if (isset($meta['pagination'])) {
                        $pagination = $meta['pagination'];
                        $this->line("  • has_more: " . ($pagination['has_more'] ?? 'N/A'));
                        $this->line("  • total: " . ($pagination['total'] ?? 'N/A'));
                        $this->line("  • page: " . ($pagination['page'] ?? 'N/A'));
                        $this->line("  • limit: " . ($pagination['limit'] ?? 'N/A'));
                        
                        $hasMore = $pagination['has_more'] ?? false;
                    } else {
                        $this->line("  • Paginación: No disponible");
                        $hasMore = false;
                    }
                } else {
                    $this->line("📋 Meta información: No disponible");
                    $hasMore = false;
                }

                // Mostrar información de contactos
                if (!empty($contacts)) {
                    $firstContact = $contacts[0];
                    $lastContact = $contacts[count($contacts) - 1];
                    
                    $this->line("👤 Primer contacto:");
                    $this->line("  • ID: " . ($firstContact['id'] ?? 'N/A'));
                    $this->line("  • Email: " . ($firstContact['email'] ?? 'N/A'));
                    $this->line("  • Nombre: " . trim(($firstContact['firstName'] ?? '') . ' ' . ($firstContact['lastName'] ?? '')));
                    
                    $this->line("👤 Último contacto:");
                    $this->line("  • ID: " . ($lastContact['id'] ?? 'N/A'));
                    $this->line("  • Email: " . ($lastContact['email'] ?? 'N/A'));
                    $this->line("  • Nombre: " . trim(($lastContact['firstName'] ?? '') . ' ' . ($lastContact['lastName'] ?? '')));
                }

                $this->newLine();

                if (!$hasMore) {
                    $this->info("🏁 No hay más páginas disponibles");
                    break;
                }

                $page++;

                // Pausa entre páginas
                usleep(200000); // 0.2 segundos
            }

            $this->info('📊 RESUMEN DEL DIAGNÓSTICO:');
            $this->info('==========================');
            $this->line("🎯 Total de contactos procesados: {$totalContacts}");
            $this->line("📄 Páginas procesadas: " . ($page - 1));
            $this->line("📊 Promedio por página: " . ($page > 1 ? round($totalContacts / ($page - 1), 2) : $totalContacts));
            
            if ($page > $maxPages) {
                $this->warn("⚠️  Se alcanzó el límite de páginas de prueba ({$maxPages})");
                $this->line("💡 Hay más contactos disponibles. Ejecuta con --pages=X para probar más páginas");
            }

            $this->info('✅ Diagnóstico completado');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el diagnóstico: " . $e->getMessage());
            Log::error('Error en diagnóstico de paginación GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
