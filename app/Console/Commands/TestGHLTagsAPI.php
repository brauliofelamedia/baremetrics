<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLTagsAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-tags-api 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--limit=100 : Límite de usuarios a procesar para la prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la búsqueda de usuarios por tags usando el método API directo';

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

        $this->info('🧪 PRUEBA DE BÚSQUEDA POR TAGS CON API DIRECTA');
        $this->info('==============================================');
        
        $this->info("🏷️  Tags a buscar: " . implode(', ', $tags));
        $this->info("📊 Límite de prueba: {$limit} usuarios");
        $this->newLine();

        try {
            $this->testAPIMethod($tags, $limit);
            
            $this->info('✅ Prueba completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            Log::error('Error en prueba de API por tags', [
                'error' => $e->getMessage(),
                'tags' => $tags
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Probar método API directo
     */
    private function testAPIMethod($tags, $limit)
    {
        $this->info('🔍 Probando método API directo...');
        
        $allContacts = [];
        $page = 1;
        $hasMore = true;
        $processedCount = 0;

        while ($hasMore && $processedCount < $limit) {
            try {
                $response = $this->ghlService->getContactsByTags($tags, $page);
                
                if (!$response) {
                    $this->warn('⚠️  No se obtuvo respuesta del método API');
                    break;
                }

                $contacts = $response['contacts'] ?? [];
                $processedCount += count($contacts);
                
                if (!empty($contacts)) {
                    $allContacts = array_merge($allContacts, $contacts);
                }

                $this->info("📊 Página {$page}: " . count($contacts) . " contactos encontrados");

                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;

                // Pequeña pausa entre requests
                usleep(100000); // 0.1 segundos

            } catch (\Exception $e) {
                $this->error("❌ Error en página {$page}: " . $e->getMessage());
                
                // Si es un error de API, mostrar más detalles
                if (strpos($e->getMessage(), '422') !== false) {
                    $this->warn('⚠️  Error 422: Posible problema con la estructura del filtro de tags');
                    $this->line('   El operador o la estructura del filtro puede no ser válida');
                } elseif (strpos($e->getMessage(), '401') !== false) {
                    $this->warn('⚠️  Error 401: Problema de autenticación');
                }
                
                break;
            }
        }

        $this->showResults($allContacts, $tags, $processedCount);
    }

    /**
     * Mostrar resultados
     */
    private function showResults($contacts, $tags, $processedCount)
    {
        $this->newLine();
        $this->info('📊 RESULTADOS DEL MÉTODO API:');
        $this->info('=============================');
        
        $this->info("• Total contactos procesados: {$processedCount}");
        $this->info("• Contactos encontrados: " . count($contacts));
        
        if (!empty($contacts)) {
            $this->newLine();
            $this->info('📋 Primeros contactos encontrados:');
            
            foreach (array_slice($contacts, 0, 5) as $index => $contact) {
                $contactTags = $contact['tags'] ?? [];
                $this->line("   " . ($index + 1) . ". Email: " . ($contact['email'] ?? 'N/A'));
                $this->line("      Nombre: " . ($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                $this->line("      Tags: " . implode(', ', $contactTags));
                $this->line("      ID: " . ($contact['id'] ?? 'N/A'));
                $this->newLine();
            }

            // Mostrar estadísticas de tags
            $this->showTagStatistics($contacts, $tags);
        } else {
            $this->warn('⚠️  No se encontraron contactos con los tags especificados');
            $this->line('   Posibles causas:');
            $this->line('   • Los tags no existen en GoHighLevel');
            $this->line('   • Problema con la estructura del filtro API');
            $this->line('   • Los usuarios están en una ubicación diferente');
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
