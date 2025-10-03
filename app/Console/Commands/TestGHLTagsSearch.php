<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLTagsSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-tags-search 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--limit=50 : Límite de usuarios a procesar para la prueba}
                           {--method=alternative : Método a usar (api o alternative)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la búsqueda de usuarios por tags en GoHighLevel';

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
        $tagsString = $this->option('tags');
        $tags = array_map('trim', explode(',', $tagsString));
        $limit = (int) $this->option('limit');
        $method = $this->option('method');

        $this->info('🧪 PRUEBA DE BÚSQUEDA POR TAGS EN GOHIGHLEVEL');
        $this->info('==============================================');
        
        $this->info("🏷️  Tags a buscar: " . implode(', ', $tags));
        $this->info("📊 Límite de prueba: {$limit} usuarios");
        $this->info("🔧 Método: {$method}");
        $this->newLine();

        try {
            if ($method === 'api') {
                $this->testApiMethod($tags, $limit);
            } else {
                $this->testAlternativeMethod($tags, $limit);
            }
            
            $this->info('✅ Prueba completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            Log::error('Error en prueba de búsqueda por tags', [
                'error' => $e->getMessage(),
                'tags' => $tags,
                'method' => $method
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Probar método API directo
     */
    private function testApiMethod($tags, $limit)
    {
        $this->info('🔍 Probando método API directo...');
        
        try {
            $response = $this->ghlService->getContactsByTags($tags, 1);
            
            if (!$response) {
                $this->warn('⚠️  No se obtuvo respuesta del método API');
                return;
            }

            $contacts = $response['contacts'] ?? [];
            $this->info("📊 Resultados del método API:");
            $this->info("   • Contactos encontrados: " . count($contacts));
            
            if (!empty($contacts)) {
                $this->info("📋 Primeros contactos encontrados:");
                foreach (array_slice($contacts, 0, 5) as $index => $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    $this->line("   " . ($index + 1) . ". Email: " . ($contact['email'] ?? 'N/A'));
                    $this->line("      Nombre: " . ($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                    $this->line("      Tags: " . implode(', ', $contactTags));
                    $this->line("      ID: " . ($contact['id'] ?? 'N/A'));
                    $this->newLine();
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Error en método API: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Probar método alternativo
     */
    private function testAlternativeMethod($tags, $limit)
    {
        $this->info('🔍 Probando método alternativo...');
        
        try {
            $response = $this->ghlService->getContactsByTagsAlternative($tags, $limit);
            
            if (!$response) {
                $this->warn('⚠️  No se obtuvo respuesta del método alternativo');
                return;
            }

            $contacts = $response['contacts'] ?? [];
            $meta = $response['meta'] ?? [];
            
            $this->info("📊 Resultados del método alternativo:");
            $this->info("   • Total procesados: " . ($meta['total_processed'] ?? 0));
            $this->info("   • Contactos encontrados: " . count($contacts));
            
            if (!empty($contacts)) {
                $this->info("📋 Primeros contactos encontrados:");
                foreach (array_slice($contacts, 0, 5) as $index => $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    $this->line("   " . ($index + 1) . ". Email: " . ($contact['email'] ?? 'N/A'));
                    $this->line("      Nombre: " . ($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                    $this->line("      Tags: " . implode(', ', $contactTags));
                    $this->line("      ID: " . ($contact['id'] ?? 'N/A'));
                    $this->newLine();
                }
            }

            // Mostrar estadísticas de tags
            $this->showTagStatistics($contacts, $tags);

        } catch (\Exception $e) {
            $this->error("❌ Error en método alternativo: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mostrar estadísticas de tags
     */
    private function showTagStatistics($contacts, $searchedTags)
    {
        $this->info('📊 ESTADÍSTICAS DE TAGS:');
        $this->info('========================');
        
        $tagCounts = [];
        $allTags = [];
        
        foreach ($contacts as $contact) {
            $contactTags = $contact['tags'] ?? [];
            $allTags = array_merge($allTags, $contactTags);
            
            foreach ($contactTags as $tag) {
                if (!isset($tagCounts[$tag])) {
                    $tagCounts[$tag] = 0;
                }
                $tagCounts[$tag]++;
            }
        }
        
        // Mostrar tags buscados
        $this->info("🏷️  Tags buscados:");
        foreach ($searchedTags as $tag) {
            $count = $tagCounts[$tag] ?? 0;
            $this->line("   • {$tag}: {$count} usuarios");
        }
        
        // Mostrar otros tags encontrados
        $otherTags = array_diff(array_keys($tagCounts), $searchedTags);
        if (!empty($otherTags)) {
            $this->newLine();
            $this->info("🏷️  Otros tags encontrados:");
            arsort($tagCounts);
            foreach ($otherTags as $tag) {
                $count = $tagCounts[$tag];
                if ($count > 0) {
                    $this->line("   • {$tag}: {$count} usuarios");
                }
            }
        }
    }
}
