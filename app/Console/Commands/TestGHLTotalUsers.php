<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLTotalUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-total-users 
                           {--limit=1000 : Límite de usuarios a procesar para la prueba}
                           {--method=optimized : Método a usar (optimized o standard)}
                           {--show-sample : Mostrar muestra de usuarios encontrados}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar obtención del total de usuarios de GoHighLevel sin filtros';

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
        $method = $this->option('method');
        $showSample = $this->option('show-sample');

        $this->info('🧪 PRUEBA DE TOTAL DE USUARIOS EN GOHIGHLEVEL');
        $this->info('=============================================');
        
        $this->info("📊 Límite de prueba: {$limit} usuarios");
        $this->info("🔧 Método: {$method}");
        $this->newLine();

        try {
            $results = $this->testTotalUsers($limit, $method, $showSample);
            
            $this->showResults($results);
            
            $this->info('✅ Prueba completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            Log::error('Error en prueba de total de usuarios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Probar obtención del total de usuarios
     */
    private function testTotalUsers($limit, $method, $showSample)
    {
        $this->info('🔍 Obteniendo usuarios sin filtros...');
        
        $allContacts = [];
        $page = 1;
        $hasMore = true;
        $processedCount = 0;
        $startTime = now();

        // Usar pageLimit optimizado
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

                // Mostrar progreso
                if ($processedCount % ($pageLimit * 2) === 0) {
                    $this->info("📊 Procesados: {$processedCount} usuarios");
                }

                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;

                // Pequeña pausa entre requests
                usleep(50000); // 0.05 segundos

            } catch (\Exception $e) {
                $this->error("❌ Error en página {$page}: " . $e->getMessage());
                break;
            }
        }

        $endTime = now();
        $duration = $startTime->diffInSeconds($endTime);

        return [
            'total_processed' => $processedCount,
            'contacts_found' => count($allContacts),
            'duration' => $duration,
            'method' => $method,
            'page_limit' => $pageLimit,
            'has_more' => $hasMore,
            'sample_contacts' => $showSample ? array_slice($allContacts, 0, 5) : [],
            'all_contacts' => $allContacts
        ];
    }

    /**
     * Mostrar resultados
     */
    private function showResults($results)
    {
        $this->newLine();
        $this->info('📊 RESULTADOS DE LA PRUEBA:');
        $this->info('============================');
        
        $this->info("• Método usado: {$results['method']}");
        $this->info("• PageLimit usado: {$results['page_limit']}");
        $this->info("• Total procesados: {$results['total_processed']}");
        $this->info("• Contactos obtenidos: {$results['contacts_found']}");
        $this->info("• Duración: {$results['duration']} segundos");
        $this->info("• Hay más páginas: " . ($results['has_more'] ? 'SÍ' : 'NO'));
        
        // Calcular velocidad
        if ($results['duration'] > 0) {
            $speed = round($results['total_processed'] / $results['duration'], 2);
            $this->info("• Velocidad: {$speed} usuarios/segundo");
        }

        // Mostrar estimación para el total
        if ($results['has_more']) {
            $this->newLine();
            $this->warn('⚠️  ADVERTENCIA: Hay más usuarios disponibles');
            $this->line('   El límite especificado se alcanzó antes de obtener todos los usuarios');
            $this->line('   Para obtener el total completo, ejecuta sin límite o con límite mayor');
        } else {
            $this->newLine();
            $this->info('✅ Todos los usuarios disponibles fueron procesados');
        }

        // Mostrar muestra de contactos si se solicita
        if (!empty($results['sample_contacts'])) {
            $this->newLine();
            $this->info('📋 MUESTRA DE CONTACTOS ENCONTRADOS:');
            $this->info('====================================');
            
            foreach ($results['sample_contacts'] as $index => $contact) {
                $contactTags = $contact['tags'] ?? [];
                $this->line("   " . ($index + 1) . ". Email: " . ($contact['email'] ?? 'N/A'));
                $this->line("      Nombre: " . ($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                $this->line("      Tags: " . implode(', ', $contactTags));
                $this->line("      ID: " . ($contact['id'] ?? 'N/A'));
                $this->line("      Estado: " . ($contact['status'] ?? 'N/A'));
                $this->newLine();
            }
        }

        // Análisis de tags
        $this->analyzeTags($results['all_contacts']);
    }

    /**
     * Analizar tags encontrados
     */
    private function analyzeTags($contacts)
    {
        $this->info('🏷️  ANÁLISIS DE TAGS:');
        $this->info('=====================');
        
        $allTags = [];
        $contactsWithTags = 0;
        $contactsWithoutTags = 0;
        $targetTags = ['creetelo_anual', 'creetelo_mensual', 'créetelo_anual', 'créetelo_mensual'];
        $targetTagCounts = array_fill_keys($targetTags, 0);
        
        foreach ($contacts as $contact) {
            $contactTags = $contact['tags'] ?? [];
            
            if (empty($contactTags)) {
                $contactsWithoutTags++;
            } else {
                $contactsWithTags++;
                
                // Recopilar todos los tags
                foreach ($contactTags as $tag) {
                    if (!isset($allTags[$tag])) {
                        $allTags[$tag] = 0;
                    }
                    $allTags[$tag]++;
                    
                    // Contar tags objetivo
                    if (in_array($tag, $targetTags)) {
                        $targetTagCounts[$tag]++;
                    }
                }
            }
        }
        
        $totalContacts = count($contacts);
        $this->info("• Total contactos analizados: {$totalContacts}");
        $this->info("• Contactos con tags: {$contactsWithTags}");
        $this->info("• Contactos sin tags: {$contactsWithoutTags}");
        
        if ($totalContacts > 0) {
            $percentageWithTags = round(($contactsWithTags / $totalContacts) * 100, 2);
            $this->info("• Porcentaje con tags: {$percentageWithTags}%");
        }
        
        $this->newLine();
        $this->info('🎯 TAGS OBJETIVO ENCONTRADOS:');
        $this->info('=============================');
        
        $totalTargetTags = 0;
        foreach ($targetTagCounts as $tag => $count) {
            $this->line("• {$tag}: {$count} contactos");
            $totalTargetTags += $count;
        }
        
        $this->info("• Total con tags objetivo: {$totalTargetTags}");
        
        if ($totalContacts > 0) {
            $percentageTarget = round(($totalTargetTags / $totalContacts) * 100, 2);
            $this->info("• Porcentaje con tags objetivo: {$percentageTarget}%");
        }

        // Mostrar tags más comunes
        if (!empty($allTags)) {
            $this->newLine();
            $this->info('🏷️  TAGS MÁS COMUNES:');
            $this->info('=====================');
            
            arsort($allTags);
            $count = 0;
            foreach ($allTags as $tag => $frequency) {
                $count++;
                $this->line("{$count}. {$tag}: {$frequency} contactos");
                
                if ($count >= 20) { // Limitar a 20 tags más comunes
                    $remaining = count($allTags) - 20;
                    if ($remaining > 0) {
                        $this->line("... y {$remaining} tags más");
                    }
                    break;
                }
            }
        }
    }
}
