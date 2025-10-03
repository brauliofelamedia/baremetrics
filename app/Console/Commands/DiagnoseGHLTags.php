<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class DiagnoseGHLTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:diagnose-tags 
                           {--limit=100 : Límite de contactos a revisar}
                           {--show-tags : Mostrar todos los tags encontrados}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosticar problemas con tags en GoHighLevel';

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
        $showTags = $this->option('show-tags');

        $this->info('🔍 DIAGNÓSTICO DE TAGS EN GOHIGHLEVEL');
        $this->info('=====================================');
        
        $this->info("📊 Límite de revisión: {$limit} contactos");
        $this->newLine();

        try {
            $this->diagnoseTagsStructure($limit, $showTags);
            
            $this->info('✅ Diagnóstico completado');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el diagnóstico: " . $e->getMessage());
            Log::error('Error en diagnóstico de tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Diagnosticar estructura de tags
     */
    private function diagnoseTagsStructure($limit, $showTags)
    {
        $this->info('🔍 Analizando estructura de tags...');
        
        $allTags = [];
        $contactsWithTags = 0;
        $contactsWithoutTags = 0;
        $targetTags = ['creetelo_anual', 'creetelo_mensual', 'créetelo_anual', 'créetelo_mensual'];
        $targetTagCounts = array_fill_keys($targetTags, 0);
        $page = 1;
        $processedCount = 0;
        $hasMore = true;

        while ($hasMore && $processedCount < $limit) {
            $response = $this->ghlService->getContacts('', $page, 100);
            
            if (!$response || empty($response['contacts'])) {
                break;
            }

            $contacts = $response['contacts'];
            $processedCount += count($contacts);

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

            // Verificar paginación
            if (isset($response['meta']['pagination'])) {
                $pagination = $response['meta']['pagination'];
                $hasMore = $pagination['has_more'] ?? false;
            } else {
                $hasMore = false;
            }

            $page++;

            // Mostrar progreso
            if ($processedCount % 100 === 0) {
                $this->info("📊 Procesados: {$processedCount} contactos");
            }

            usleep(100000); // 0.1 segundos
        }

        // Mostrar resultados
        $this->showDiagnosticResults($processedCount, $contactsWithTags, $contactsWithoutTags, $targetTagCounts, $allTags, $showTags);
    }

    /**
     * Mostrar resultados del diagnóstico
     */
    private function showDiagnosticResults($processedCount, $contactsWithTags, $contactsWithoutTags, $targetTagCounts, $allTags, $showTags)
    {
        $this->newLine();
        $this->info('📊 RESULTADOS DEL DIAGNÓSTICO:');
        $this->info('==============================');
        
        $this->info("• Total contactos procesados: {$processedCount}");
        $this->info("• Contactos con tags: {$contactsWithTags}");
        $this->info("• Contactos sin tags: {$contactsWithoutTags}");
        $this->newLine();

        $this->info('🎯 TAGS OBJETIVO ENCONTRADOS:');
        $this->info('=============================');
        
        $totalTargetTags = 0;
        foreach ($targetTagCounts as $tag => $count) {
            $this->line("• {$tag}: {$count} contactos");
            $totalTargetTags += $count;
        }
        
        $this->info("• Total con tags objetivo: {$totalTargetTags}");
        $this->newLine();

        if ($showTags) {
            $this->info('🏷️  TODOS LOS TAGS ENCONTRADOS:');
            $this->info('===============================');
            
            // Ordenar por frecuencia
            arsort($allTags);
            
            $count = 0;
            foreach ($allTags as $tag => $frequency) {
                $count++;
                $this->line("{$count}. {$tag}: {$frequency} contactos");
                
                if ($count >= 50) { // Limitar a 50 tags más comunes
                    $remaining = count($allTags) - 50;
                    if ($remaining > 0) {
                        $this->line("... y {$remaining} tags más");
                    }
                    break;
                }
            }
            $this->newLine();
        }

        // Análisis de problemas
        $this->analyzePotentialIssues($totalTargetTags, $processedCount, $contactsWithTags);
    }

    /**
     * Analizar posibles problemas
     */
    private function analyzePotentialIssues($totalTargetTags, $processedCount, $contactsWithTags)
    {
        $this->info('🔍 ANÁLISIS DE PROBLEMAS:');
        $this->info('=========================');
        
        if ($totalTargetTags === 0) {
            $this->warn('⚠️  PROBLEMA: No se encontraron usuarios con los tags objetivo');
            $this->line('   Posibles causas:');
            $this->line('   • Los tags no existen en GoHighLevel');
            $this->line('   • Los tags tienen diferente escritura');
            $this->line('   • Los usuarios están en una ubicación diferente');
            $this->line('   • Los tags están en un campo diferente');
        } elseif ($totalTargetTags < 10) {
            $this->warn('⚠️  PROBLEMA: Muy pocos usuarios encontrados con los tags objetivo');
            $this->line('   Posibles causas:');
            $this->line('   • Los tags son poco comunes');
            $this->line('   • Hay usuarios con tags similares pero no exactos');
        } else {
            $this->info('✅ Los tags objetivo se están encontrando correctamente');
        }

        if ($contactsWithTags === 0) {
            $this->error('❌ PROBLEMA CRÍTICO: Ningún contacto tiene tags');
            $this->line('   Posibles causas:');
            $this->line('   • Los tags no se están cargando correctamente');
            $this->line('   • Problema con la API de GoHighLevel');
            $this->line('   • Los contactos no tienen tags asignados');
        }

        $percentage = $processedCount > 0 ? round(($contactsWithTags / $processedCount) * 100, 2) : 0;
        $this->info("• Porcentaje de contactos con tags: {$percentage}%");
        
        if ($percentage < 10) {
            $this->warn('⚠️  ADVERTENCIA: Muy pocos contactos tienen tags');
        }
    }
}
