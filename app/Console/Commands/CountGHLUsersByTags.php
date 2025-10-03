<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CountGHLUsersByTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:count-users-by-tags 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--max-pages=100 : Máximo número de páginas a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cuenta el total de usuarios de GoHighLevel que tienen al menos uno de los tags especificados (filtro OR)';

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
        $tagsString = $this->option('tags');
        $tags = array_map('trim', explode(',', $tagsString));
        $maxPages = (int) $this->option('max-pages');

        $this->info('🔢 CONTANDO USUARIOS DE GOHIGHLEVEL POR TAGS (FILTRO OR)');
        $this->info('======================================================');
        
        $this->info("🏷️  Tags a buscar: " . implode(', ', $tags));
        $this->info("📄 Máximo de páginas: {$maxPages}");

        try {
            $totalUsers = 0;
            $totalProcessed = 0;
            $page = 1;
            $hasMore = true;
            $uniqueUserIds = []; // Para evitar duplicados
            
            while ($hasMore && $page <= $maxPages) {
                $this->info("📄 Procesando página {$page}...");
                
                $response = $this->ghlService->getContactsByTags($tags, $page);
                
                if (!$response || empty($response['contacts'])) {
                    $this->info("   No hay más contactos en la página {$page}");
                    break;
                }
                
                $contacts = $response['contacts'];
                $totalProcessed += count($contacts);
                
                // Contar usuarios que tengan al menos uno de los tags (OR lógico)
                $pageMatches = 0;
                foreach ($contacts as $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    if (!empty(array_intersect($tags, $contactTags))) {
                        // Evitar duplicados usando el ID del usuario
                        if (!in_array($contact['id'], $uniqueUserIds)) {
                            $uniqueUserIds[] = $contact['id'];
                            $totalUsers++;
                            $pageMatches++;
                        }
                    }
                }
                
                $this->info("   Página {$page}: {$pageMatches} usuarios nuevos encontrados");
                $this->info("   Total acumulado: {$totalUsers} usuarios únicos");
                
                // Verificar si hay más páginas
                $nextPageResponse = $this->ghlService->getContactsByTags($tags, $page + 1);
                if ($nextPageResponse && !empty($nextPageResponse['contacts'])) {
                    $hasMore = true;
                } else {
                    $hasMore = false;
                    $this->info("   No hay más páginas disponibles");
                }
                
                $page++;
                
                // Pequeña pausa para evitar rate limiting
                usleep(200000); // 0.2 segundos
            }
            
            $duration = $startTime->diffInSeconds(now());
            
            $this->newLine();
            $this->info('✅ CONTEO COMPLETADO');
            $this->info('==================');
            $this->info("🏷️  Tags buscados: " . implode(', ', $tags));
            $this->info("👥 Total usuarios únicos encontrados: {$totalUsers}");
            $this->info("📊 Total contactos procesados: {$totalProcessed}");
            $this->info("📄 Páginas procesadas: " . ($page - 1));
            $this->info("⏱️  Tiempo total: {$duration} segundos");
            
            if ($totalUsers > 0) {
                $efficiency = round(($totalUsers / $totalProcessed) * 100, 2);
                $this->info("📈 Eficiencia: {$efficiency}%");
            }
            
            // Mostrar estadísticas por tag
            $this->newLine();
            $this->info('📋 ESTADÍSTICAS POR TAG:');
            $this->info('========================');
            
            foreach ($tags as $tag) {
                $this->info("🔍 Contando usuarios con tag '{$tag}'...");
                $tagCount = $this->countUsersBySingleTag($tag);
                $this->info("   Tag '{$tag}': {$tagCount} usuarios");
            }
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error en conteo GHL por tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }
    }
    
    /**
     * Contar usuarios con un tag específico
     */
    private function countUsersBySingleTag($tag)
    {
        try {
            $total = 0;
            $page = 1;
            $hasMore = true;
            $uniqueUserIds = [];
            
            while ($hasMore && $page <= 50) { // Límite de 50 páginas por tag
                $response = $this->ghlService->getContactsByTags([$tag], $page);
                
                if (!$response || empty($response['contacts'])) {
                    break;
                }
                
                foreach ($response['contacts'] as $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    if (in_array($tag, $contactTags)) {
                        if (!in_array($contact['id'], $uniqueUserIds)) {
                            $uniqueUserIds[] = $contact['id'];
                            $total++;
                        }
                    }
                }
                
                // Verificar si hay más páginas
                $nextPageResponse = $this->ghlService->getContactsByTags([$tag], $page + 1);
                $hasMore = $nextPageResponse && !empty($nextPageResponse['contacts']);
                $page++;
                
                usleep(100000); // 0.1 segundos
            }
            
            return $total;
            
        } catch (\Exception $e) {
            Log::warning("Error contando usuarios por tag individual", [
                'tag' => $tag,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
