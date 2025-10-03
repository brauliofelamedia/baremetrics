<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GetMissingUsersFromBaremetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:missing-users 
                            {--tags=creetelo_mensual,creetelo_anual : Tags de GHL a incluir}
                            {--exclude-tags=unsubscribe : Tags a excluir}
                            {--limit=50 : Límite de usuarios a procesar}
                            {--output=table : Formato de salida (table, json, csv)}
                            {--save : Guardar resultado en archivo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtiene la lista de usuarios de GHL que NO están incluidos en Baremetrics';

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
        $this->info('🔍 Buscando usuarios de GHL que NO están en Baremetrics...');
        
        // Obtener parámetros
        $tagsString = $this->option('tags');
        $excludeTagsString = $this->option('exclude-tags');
        $limit = (int) $this->option('limit');
        $outputFormat = $this->option('output');
        $saveFile = $this->option('save');

        // Convertir strings a arrays
        $tags = array_map('trim', explode(',', $tagsString));
        $excludeTags = array_map('trim', explode(',', $excludeTagsString));

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");
        $this->line("   • Formato: {$outputFormat}");

        try {
            // 1. Obtener usuarios de GHL
            $this->info("\n🔍 Obteniendo usuarios de GHL...");
            $ghlUsers = $this->getGHLUsersByTags($tags, $excludeTags, $limit);
            
            if (empty($ghlUsers)) {
                $this->error('❌ No se encontraron usuarios de GHL con los tags especificados');
                return 1;
            }

            $this->info("✅ Encontrados " . count($ghlUsers) . " usuarios de GHL");

            // 2. Obtener usuarios de Baremetrics
            $this->info("\n🔍 Obteniendo usuarios de Baremetrics...");
            $baremetricsEmails = $this->getBaremetricsEmails();
            
            if (empty($baremetricsEmails)) {
                $this->warn('⚠️ No se pudieron obtener usuarios de Baremetrics');
                $baremetricsEmails = [];
            } else {
                $this->info("✅ Encontrados " . count($baremetricsEmails) . " usuarios de Baremetrics");
            }

            // 3. Filtrar usuarios faltantes
            $this->info("\n🔄 Identificando usuarios faltantes...");
            $missingUsers = $this->findMissingUsers($ghlUsers, $baremetricsEmails);

            // 4. Mostrar resultados
            $this->displayMissingUsers($missingUsers, $outputFormat);

            // 5. Guardar archivo si se solicita
            if ($saveFile) {
                $this->saveMissingUsersToFile($missingUsers, $outputFormat);
            }

            $this->info("\n✅ Análisis completado!");
            $this->line("📊 Resumen: " . count($missingUsers) . " usuarios de GHL no están en Baremetrics");

        } catch (\Exception $e) {
            $this->error("❌ Error durante el análisis: " . $e->getMessage());
            Log::error('Error en análisis de usuarios faltantes', [
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
    private function getGHLUsersByTags(array $tags, array $excludeTags, int $limit): array
    {
        $allUsers = [];
        $processedCount = 0;

        foreach ($tags as $tag) {
            $this->line("   📄 Procesando tag: {$tag}");
            
            try {
                $response = $this->ghlService->getContactsByTagsOptimized([$tag], $limit);
                
                if ($response && isset($response['contacts'])) {
                    $users = $response['contacts'];
                    $processedCount += count($users);
                    
                    foreach ($users as $user) {
                        if (!empty($user['email']) && $this->isValidEmail($user['email'])) {
                            $userTags = $user['tags'] ?? [];
                            
                            // Verificar si tiene tags excluidos
                            $hasExcludedTags = !empty(array_intersect($excludeTags, $userTags));
                            
                            if (!$hasExcludedTags) {
                                $allUsers[] = [
                                    'id' => $user['id'],
                                    'name' => $user['name'] ?? 'Sin nombre',
                                    'email' => strtolower(trim($user['email'])),
                                    'tags' => $userTags,
                                    'phone' => $user['phone'] ?? '',
                                    'company' => $user['companyName'] ?? ''
                                ];
                            }
                        }
                    }
                    
                    $this->line("     • {$tag}: " . count($users) . " usuarios procesados");
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

        $this->line("   📊 Total procesados: {$processedCount}");
        $this->line("   📊 Únicos encontrados: " . count($uniqueUsers));

        return $uniqueUsers;
    }

    /**
     * Obtener emails de usuarios de Baremetrics
     */
    private function getBaremetricsEmails(): array
    {
        $emails = [];
        
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
                $sourceCount = 0;
                
                while ($hasMore) {
                    $response = $this->baremetricsService->getCustomersAll($sourceId, $page);
                    
                    if (!$response || empty($response['customers'])) {
                        break;
                    }
                    
                    $customers = $response['customers'];
                    
                    foreach ($customers as $customer) {
                        if (!empty($customer['email']) && $this->isValidEmail($customer['email'])) {
                            $emails[] = strtolower(trim($customer['email']));
                            $sourceCount++;
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
                    usleep(50000); // Pausa más pequeña
                }
                
                $this->line("     • {$sourceId}: {$sourceCount} usuarios");
            }
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Error obteniendo usuarios de Baremetrics: " . $e->getMessage());
            return [];
        }

        // Eliminar duplicados
        $emails = array_unique($emails);
        
        return $emails;
    }

    /**
     * Encontrar usuarios de GHL que no están en Baremetrics
     */
    private function findMissingUsers(array $ghlUsers, array $baremetricsEmails): array
    {
        $missingUsers = [];
        
        foreach ($ghlUsers as $user) {
            if (!in_array($user['email'], $baremetricsEmails)) {
                $missingUsers[] = $user;
            }
        }
        
        return $missingUsers;
    }

    /**
     * Mostrar usuarios faltantes
     */
    private function displayMissingUsers(array $missingUsers, string $format): void
    {
        if (empty($missingUsers)) {
            $this->info("\n✅ ¡Excelente! Todos los usuarios de GHL están en Baremetrics");
            return;
        }

        $this->warn("\n⚠️ USUARIOS DE GHL FALTANTES EN BAREMETRICS:");
        $this->line("=============================================");
        $this->line("Total faltantes: " . count($missingUsers));
        
        if ($format === 'table') {
            $headers = ['Email', 'Nombre', 'Teléfono', 'Empresa', 'Tags'];
            $rows = [];
            
            foreach ($missingUsers as $user) {
                $rows[] = [
                    $user['email'],
                    $user['name'],
                    $user['phone'],
                    $user['company'],
                    implode(', ', $user['tags'])
                ];
            }
            
            $this->table($headers, $rows);
        } elseif ($format === 'json') {
            $this->line(json_encode($missingUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($missingUsers as $user) {
                $this->line("   • {$user['email']} - {$user['name']} - Tags: " . implode(', ', $user['tags']));
            }
        }
        
        // Mostrar estadísticas por tag
        $this->showTagStatistics($missingUsers);
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
     * Guardar usuarios faltantes en archivo
     */
    private function saveMissingUsersToFile(array $missingUsers, string $format): void
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "usuarios-faltantes-baremetrics-{$timestamp}";
        
        if ($format === 'json') {
            $filename .= '.json';
            $content = json_encode($missingUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $filename .= '.csv';
            $content = $this->generateCSV($missingUsers);
        }
        
        Storage::put($filename, $content);
        
        $this->info("\n💾 Lista guardada en: storage/{$filename}");
    }

    /**
     * Generar contenido CSV
     */
    private function generateCSV(array $missingUsers): string
    {
        $csv = "Email,Nombre,Telefono,Empresa,Tags\n";
        
        foreach ($missingUsers as $user) {
            $csv .= "\"{$user['email']}\",\"{$user['name']}\",\"{$user['phone']}\",\"{$user['company']}\",\"" . implode(', ', $user['tags']) . "\"\n";
        }
        
        return $csv;
    }

    /**
     * Validar si un email es válido
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
