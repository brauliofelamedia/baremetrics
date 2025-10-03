<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CompareGHLUsersWithBaremetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:compare-with-baremetrics 
                            {--tags=* : Tags de GHL a incluir en la comparación}
                            {--exclude-tags=* : Tags a excluir de la comparación}
                            {--limit=100 : Límite de usuarios de GHL a procesar}
                            {--output=json : Formato de salida (json, csv, table)}
                            {--save-file : Guardar resultado en archivo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara usuarios de GHL con usuarios de Baremetrics para identificar usuarios faltantes';

    private GoHighLevelService $ghlService;
    private BaremetricsService $baremetricsService;

    public function __construct(GoHighLevelService $ghlService, BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Iniciando comparación de usuarios GHL vs Baremetrics...');
        
        // Obtener parámetros
        $tags = $this->option('tags') ?: ['creetelo_mensual', 'creetelo_anual', 'créetelo_mensual', 'créetelo_anual'];
        $excludeTags = $this->option('exclude-tags') ?: ['unsubscribe'];
        $limit = (int) $this->option('limit');
        $outputFormat = $this->option('output');
        $saveFile = $this->option('save-file');

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");
        $this->line("   • Formato: {$outputFormat}");

        try {
            // 1. Obtener usuarios de GHL
            $this->info("\n🔍 Obteniendo usuarios de GHL...");
            $ghlUsers = $this->getGHLUsers($tags, $excludeTags, $limit);
            
            if (empty($ghlUsers)) {
                $this->error('❌ No se encontraron usuarios de GHL con los tags especificados');
                return 1;
            }

            $this->info("✅ Encontrados {$ghlUsers['total']} usuarios de GHL");

            // 2. Obtener usuarios de Baremetrics
            $this->info("\n🔍 Obteniendo usuarios de Baremetrics...");
            $baremetricsUsers = $this->getBaremetricsUsers();
            
            if (empty($baremetricsUsers)) {
                $this->error('❌ No se pudieron obtener usuarios de Baremetrics');
                return 1;
            }

            $this->info("✅ Encontrados {$baremetricsUsers['total']} usuarios de Baremetrics");

            // 3. Comparar usuarios
            $this->info("\n🔄 Comparando usuarios...");
            $comparison = $this->compareUsers($ghlUsers['users'], $baremetricsUsers['users']);

            // 4. Mostrar resultados
            $this->displayResults($comparison, $outputFormat);

            // 5. Guardar archivo si se solicita
            if ($saveFile) {
                $this->saveResultsToFile($comparison, $outputFormat);
            }

            $this->info("\n✅ Comparación completada exitosamente!");

        } catch (\Exception $e) {
            $this->error("❌ Error durante la comparación: " . $e->getMessage());
            Log::error('Error en comparación GHL vs Baremetrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Obtener usuarios de GHL filtrados por tags
     */
    private function getGHLUsers(array $tags, array $excludeTags, int $limit): array
    {
        $allUsers = [];
        $totalProcessed = 0;

        foreach ($tags as $tag) {
            $this->line("   📄 Procesando tag: {$tag}");
            
            try {
                $response = $this->ghlService->getContactsByTagsOptimized([$tag], $limit);
                
                if ($response && isset($response['contacts'])) {
                    $users = $response['contacts'];
                    $totalProcessed += count($users);
                    
                    // Filtrar usuarios con email válido y excluir tags no deseados
                    foreach ($users as $user) {
                        if (!empty($user['email']) && $this->hasValidEmail($user['email'])) {
                            $userTags = $user['tags'] ?? [];
                            
                            // Verificar si tiene tags excluidos
                            $hasExcludedTags = !empty(array_intersect($excludeTags, $userTags));
                            
                            if (!$hasExcludedTags) {
                                $allUsers[] = [
                                    'id' => $user['id'],
                                    'name' => $user['name'] ?? 'Sin nombre',
                                    'email' => strtolower(trim($user['email'])),
                                    'tags' => $userTags,
                                    'source' => 'GHL'
                                ];
                            }
                        }
                    }
                    
                    $this->line("     • {$tag}: " . count($users) . " usuarios");
                }
            } catch (\Exception $e) {
                $this->warn("     ⚠️ Error procesando tag {$tag}: " . $e->getMessage());
            }
        }

        // Eliminar duplicados por email
        $uniqueUsers = [];
        $emails = [];
        
        foreach ($allUsers as $user) {
            if (!in_array($user['email'], $emails)) {
                $uniqueUsers[] = $user;
                $emails[] = $user['email'];
            }
        }

        return [
            'users' => $uniqueUsers,
            'total' => count($uniqueUsers),
            'processed' => $totalProcessed
        ];
    }

    /**
     * Obtener usuarios de Baremetrics
     */
    private function getBaremetricsUsers(): array
    {
        $allUsers = [];
        
        try {
            // Obtener sources
            $sources = $this->baremetricsService->getSources();
            
            if (!$sources || empty($sources['sources'])) {
                throw new \Exception('No se encontraron sources en Baremetrics');
            }

            // Procesar cada source
            foreach ($sources['sources'] as $source) {
                $sourceId = $source['id'];
                $this->line("   📄 Procesando source: {$sourceId}");
                
                $page = 0;
                $hasMore = true;
                
                while ($hasMore) {
                    $response = $this->baremetricsService->getCustomersAll($sourceId, $page);
                    
                    if (!$response || empty($response['customers'])) {
                        break;
                    }
                    
                    $customers = $response['customers'];
                    
                    foreach ($customers as $customer) {
                        if (!empty($customer['email']) && $this->hasValidEmail($customer['email'])) {
                            $allUsers[] = [
                                'id' => $customer['id'],
                                'name' => $customer['name'] ?? 'Sin nombre',
                                'email' => strtolower(trim($customer['email'])),
                                'source' => 'Baremetrics',
                                'source_id' => $sourceId
                            ];
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
                    usleep(100000); // Pausa entre requests
                }
                
                $this->line("     • {$sourceId}: " . count($allUsers) . " usuarios totales");
            }
            
        } catch (\Exception $e) {
            throw new \Exception("Error obteniendo usuarios de Baremetrics: " . $e->getMessage());
        }

        return [
            'users' => $allUsers,
            'total' => count($allUsers)
        ];
    }

    /**
     * Comparar usuarios de GHL con usuarios de Baremetrics
     */
    private function compareUsers(array $ghlUsers, array $baremetricsUsers): array
    {
        $ghlEmails = array_column($ghlUsers, 'email');
        $baremetricsEmails = array_column($baremetricsUsers, 'email');
        
        // Usuarios de GHL que NO están en Baremetrics
        $missingInBaremetrics = [];
        foreach ($ghlUsers as $user) {
            if (!in_array($user['email'], $baremetricsEmails)) {
                $missingInBaremetrics[] = $user;
            }
        }
        
        // Usuarios de Baremetrics que NO están en GHL
        $missingInGHL = [];
        foreach ($baremetricsUsers as $user) {
            if (!in_array($user['email'], $ghlEmails)) {
                $missingInGHL[] = $user;
            }
        }
        
        // Usuarios que están en ambos
        $commonUsers = [];
        foreach ($ghlUsers as $user) {
            if (in_array($user['email'], $baremetricsEmails)) {
                $commonUsers[] = $user;
            }
        }

        return [
            'ghl_total' => count($ghlUsers),
            'baremetrics_total' => count($baremetricsUsers),
            'missing_in_baremetrics' => $missingInBaremetrics,
            'missing_in_ghl' => $missingInGHL,
            'common_users' => $commonUsers,
            'missing_count' => count($missingInBaremetrics),
            'common_count' => count($commonUsers)
        ];
    }

    /**
     * Mostrar resultados de la comparación
     */
    private function displayResults(array $comparison, string $format): void
    {
        $this->info("\n📊 RESULTADOS DE LA COMPARACIÓN");
        $this->line("=====================================");
        
        $this->line("👥 Usuarios GHL: {$comparison['ghl_total']}");
        $this->line("👥 Usuarios Baremetrics: {$comparison['baremetrics_total']}");
        $this->line("✅ Usuarios en ambos: {$comparison['common_count']}");
        $this->line("❌ Usuarios GHL faltantes en Baremetrics: {$comparison['missing_count']}");
        
        if ($comparison['missing_count'] > 0) {
            $this->warn("\n⚠️ USUARIOS DE GHL FALTANTES EN BAREMETRICS:");
            $this->line("=============================================");
            
            if ($format === 'table') {
                $headers = ['Email', 'Nombre', 'Tags'];
                $rows = [];
                
                foreach ($comparison['missing_in_baremetrics'] as $user) {
                    $rows[] = [
                        $user['email'],
                        $user['name'],
                        implode(', ', $user['tags'])
                    ];
                }
                
                $this->table($headers, $rows);
            } else {
                foreach ($comparison['missing_in_baremetrics'] as $user) {
                    $this->line("   • {$user['email']} - {$user['name']} - Tags: " . implode(', ', $user['tags']));
                }
            }
        }
        
        // Mostrar estadísticas por tag
        $this->showTagStatistics($comparison['missing_in_baremetrics']);
    }

    /**
     * Mostrar estadísticas por tag
     */
    private function showTagStatistics(array $missingUsers): void
    {
        $tagStats = [];
        
        foreach ($missingUsers as $user) {
            foreach ($user['tags'] as $tag) {
                if (!isset($tagStats[$tag])) {
                    $tagStats[$tag] = 0;
                }
                $tagStats[$tag]++;
            }
        }
        
        if (!empty($tagStats)) {
            $this->info("\n📈 ESTADÍSTICAS POR TAG:");
            $this->line("========================");
            
            arsort($tagStats);
            foreach ($tagStats as $tag => $count) {
                $this->line("   • {$tag}: {$count} usuarios faltantes");
            }
        }
    }

    /**
     * Guardar resultados en archivo
     */
    private function saveResultsToFile(array $comparison, string $format): void
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "ghl-baremetrics-comparison-{$timestamp}";
        
        if ($format === 'json') {
            $filename .= '.json';
            $content = json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $filename .= '.csv';
            $content = $this->generateCSV($comparison);
        }
        
        Storage::put($filename, $content);
        
        $this->info("\n💾 Resultados guardados en: storage/{$filename}");
    }

    /**
     * Generar contenido CSV
     */
    private function generateCSV(array $comparison): string
    {
        $csv = "Email,Nombre,Tags,Estado\n";
        
        foreach ($comparison['missing_in_baremetrics'] as $user) {
            $csv .= "\"{$user['email']}\",\"{$user['name']}\",\"" . implode(', ', $user['tags']) . "\",Faltante en Baremetrics\n";
        }
        
        return $csv;
    }

    /**
     * Validar si un email es válido
     */
    private function hasValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
