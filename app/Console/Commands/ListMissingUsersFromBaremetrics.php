<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class ListMissingUsersFromBaremetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:list-missing-users 
                            {--tags=creetelo_mensual,creetelo_anual : Tags de GHL separados por coma}
                            {--exclude-tags=unsubscribe : Tags a excluir separados por coma}
                            {--limit=100 : Límite de usuarios a procesar}
                            {--format=table : Formato de salida (table, list, json)}
                            {--save : Guardar resultado en archivo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista usuarios de GHL que NO están incluidos en Baremetrics basándose en correo electrónico';

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
        $this->info('🔍 Analizando usuarios de GHL vs Baremetrics...');
        
        // Obtener parámetros
        $tagsString = $this->option('tags');
        $excludeTagsString = $this->option('exclude-tags');
        $limit = (int) $this->option('limit');
        $format = $this->option('format');
        $saveFile = $this->option('save');

        // Convertir strings a arrays
        $tags = array_map('trim', explode(',', $tagsString));
        $excludeTags = array_map('trim', explode(',', $excludeTagsString));

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");
        $this->line("   • Formato: {$format}");

        try {
            // 1. Obtener usuarios de GHL
            $this->info("\n🔍 Obteniendo usuarios de GHL...");
            $ghlUsers = $this->getGHLUsers($tags, $excludeTags, $limit);
            
            if (empty($ghlUsers)) {
                $this->error('❌ No se encontraron usuarios de GHL con los tags especificados');
                return 1;
            }

            $this->info("✅ Encontrados " . count($ghlUsers) . " usuarios de GHL");

            // 2. Obtener emails de Baremetrics
            $this->info("\n🔍 Obteniendo emails de Baremetrics...");
            $baremetricsEmails = $this->getBaremetricsEmails();
            
            $this->info("✅ Encontrados " . count($baremetricsEmails) . " emails en Baremetrics");

            // 3. Identificar usuarios faltantes
            $this->info("\n🔄 Identificando usuarios faltantes...");
            $missingUsers = $this->identifyMissingUsers($ghlUsers, $baremetricsEmails);

            // 4. Mostrar resultados
            $this->displayResults($missingUsers, $format);

            // 5. Guardar archivo si se solicita
            if ($saveFile) {
                $this->saveToFile($missingUsers, $format);
            }

            $this->info("\n✅ Análisis completado!");
            $this->line("📊 Total usuarios GHL: " . count($ghlUsers));
            $this->line("📊 Total emails Baremetrics: " . count($baremetricsEmails));
            $this->line("📊 Usuarios faltantes: " . count($missingUsers));

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
     * Obtener usuarios de GHL filtrados por tags (operador OR)
     */
    private function getGHLUsers(array $tags, array $excludeTags, int $limit): array
    {
        $this->line("   📄 Procesando tags con operador OR: " . implode(', ', $tags));
        
        try {
            $allUsers = [];
            $processedCount = 0;
            
            // Obtener contactos usando getContacts y filtrar localmente por tags
            $this->line("     🔍 Obteniendo contactos y filtrando por tags localmente...");
            
            $page = 1;
            $hasMore = true;
            $processedCount = 0;
            $pageLimit = 100;
            
            $maxPages = 50; // Límite de páginas para evitar bucles infinitos
            
            while ($processedCount < 5000 && $page <= $maxPages) {
                $response = $this->ghlService->getContacts('', $page, $pageLimit);
                
                if (!$response || empty($response['contacts'])) {
                    $this->line("       • No hay más contactos disponibles en la página {$page}");
                    break;
                }

                $contacts = $response['contacts'];
                $processedCount += count($contacts);
                
                // Filtrar contactos por tags
                foreach ($contacts as $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    
                    // Verificar si tiene alguno de los tags especificados (OR logic)
                    $hasMatchingTag = !empty(array_intersect($tags, $contactTags));
                    
                    if ($hasMatchingTag) {
                        if (!empty($contact['email']) && $this->isValidEmail($contact['email'])) {
                            $userTags = $contactTags;
                            
                            // Verificar si tiene tags excluidos
                            $hasExcludedTags = !empty(array_intersect($excludeTags, $userTags));
                            
                            if (!$hasExcludedTags) {
                                // Verificar si ya existe (evitar duplicados)
                                $exists = false;
                                foreach ($allUsers as $existingUser) {
                                    if ($existingUser['email'] === strtolower(trim($contact['email']))) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                
                                if (!$exists) {
                                    $allUsers[] = [
                                        'id' => $contact['id'],
                                        'name' => $contact['name'] ?? 'Sin nombre',
                                        'email' => strtolower(trim($contact['email'])),
                                        'tags' => $userTags,
                                        'phone' => $contact['phone'] ?? '',
                                        'company' => $contact['companyName'] ?? ''
                                    ];
                                }
                            }
                        }
                    }
                }
                
                // Incrementar página para continuar
                $page++;
                
                // Pausa pequeña entre requests
                usleep(100000);
                
                // Mostrar progreso cada 500 contactos procesados
                if ($processedCount % 500 === 0) {
                    $this->line("       • Progreso: {$processedCount} contactos procesados, " . count($allUsers) . " usuarios válidos encontrados");
                }
            }
            
            $this->line("     • Total contactos procesados: {$processedCount}");
            $this->line("     • Usuarios válidos encontrados: " . count($allUsers));

            return $allUsers;
            
        } catch (\Exception $e) {
            $this->warn("     ⚠️ Error procesando tags: " . $e->getMessage());
            return [];
        }
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
                    usleep(50000); // Pausa pequeña
                }
                
                $this->line("     • {$sourceId}: {$sourceCount} emails");
            }
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Error obteniendo emails de Baremetrics: " . $e->getMessage());
            return [];
        }

        // Eliminar duplicados
        $emails = array_unique($emails);
        
        return $emails;
    }

    /**
     * Identificar usuarios de GHL que no están en Baremetrics
     */
    private function identifyMissingUsers(array $ghlUsers, array $baremetricsEmails): array
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
     * Mostrar resultados
     */
    private function displayResults(array $missingUsers, string $format): void
    {
        // Obtener usuarios que SÍ están en ambos sistemas
        $ghlUsers = $this->getGHLUsers(
            array_map('trim', explode(',', $this->option('tags'))),
            array_map('trim', explode(',', $this->option('exclude-tags'))),
            (int) $this->option('limit')
        );
        
        $baremetricsEmails = $this->getBaremetricsEmails();
        $commonUsers = [];
        
        foreach ($ghlUsers as $user) {
            if (in_array($user['email'], $baremetricsEmails)) {
                $commonUsers[] = $user;
            }
        }

        // Mostrar resumen completo
        $this->showCompleteSummary($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers);

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
     * Mostrar resumen completo de la comparación
     */
    private function showCompleteSummary(array $ghlUsers, array $baremetricsEmails, array $commonUsers, array $missingUsers): void
    {
        $this->info("\n📊 RESUMEN COMPLETO DE LA COMPARACIÓN");
        $this->line("=====================================");
        
        $this->line("👥 Total usuarios GHL (filtrados): " . count($ghlUsers));
        $this->line("👥 Total emails Baremetrics: " . count($baremetricsEmails));
        $this->line("✅ Usuarios en AMBOS sistemas: " . count($commonUsers));
        $this->line("❌ Usuarios GHL faltantes en Baremetrics: " . count($missingUsers));
        
        // Calcular porcentajes
        if (count($ghlUsers) > 0) {
            $percentageInBoth = round((count($commonUsers) / count($ghlUsers)) * 100, 2);
            $percentageMissing = round((count($missingUsers) / count($ghlUsers)) * 100, 2);
            
            $this->line("\n📈 PORCENTAJES:");
            $this->line("   • Sincronizados: {$percentageInBoth}%");
            $this->line("   • Faltantes: {$percentageMissing}%");
        }

        // Mostrar usuarios que SÍ están en ambos sistemas
        if (!empty($commonUsers)) {
            $this->info("\n✅ USUARIOS QUE SÍ ESTÁN EN AMBOS SISTEMAS:");
            $this->line("==========================================");
            
            foreach ($commonUsers as $user) {
                $this->line("   • {$user['email']} - {$user['name']} - Tags: " . implode(', ', $user['tags']));
            }
        }

        // Mostrar estadísticas por tag para usuarios comunes
        if (!empty($commonUsers)) {
            $this->showTagStatisticsForUsers($commonUsers, "USUARIOS SINCRONIZADOS");
        }
    }

    /**
     * Mostrar estadísticas por tag para usuarios faltantes
     */
    private function showTagStatistics(array $missingUsers): void
    {
        $this->showTagStatisticsForUsers($missingUsers, "USUARIOS FALTANTES");
    }

    /**
     * Mostrar estadísticas por tag para cualquier grupo de usuarios
     */
    private function showTagStatisticsForUsers(array $users, string $title): void
    {
        $tagStats = [];
        
        foreach ($users as $user) {
            foreach ($user['tags'] as $tag) {
                if (!isset($tagStats[$tag])) {
                    $tagStats[$tag] = 0;
                }
                $tagStats[$tag]++;
            }
        }
        
        if (!empty($tagStats)) {
            $this->info("\n📈 ESTADÍSTICAS POR TAG - {$title}:");
            $this->line("=====================================");
            
            arsort($tagStats);
            foreach ($tagStats as $tag => $count) {
                $this->line("   • {$tag}: {$count} usuarios");
            }
        }
    }

    /**
     * Guardar resultados en archivo
     */
    private function saveToFile(array $missingUsers, string $format): void
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "comparacion-completa-ghl-baremetrics-{$timestamp}";
        
        // Obtener datos completos para el archivo
        $ghlUsers = $this->getGHLUsers(
            array_map('trim', explode(',', $this->option('tags'))),
            array_map('trim', explode(',', $this->option('exclude-tags'))),
            (int) $this->option('limit')
        );
        
        $baremetricsEmails = $this->getBaremetricsEmails();
        $commonUsers = [];
        
        foreach ($ghlUsers as $user) {
            if (in_array($user['email'], $baremetricsEmails)) {
                $commonUsers[] = $user;
            }
        }

        if ($format === 'json') {
            $filename .= '.json';
            $completeData = [
                'resumen' => [
                    'total_ghl' => count($ghlUsers),
                    'total_baremetrics' => count($baremetricsEmails),
                    'usuarios_comunes' => count($commonUsers),
                    'usuarios_faltantes' => count($missingUsers),
                    'porcentaje_sincronizados' => count($ghlUsers) > 0 ? round((count($commonUsers) / count($ghlUsers)) * 100, 2) : 0,
                    'porcentaje_faltantes' => count($ghlUsers) > 0 ? round((count($missingUsers) / count($ghlUsers)) * 100, 2) : 0
                ],
                'usuarios_comunes' => $commonUsers,
                'usuarios_faltantes' => $missingUsers,
                'fecha_analisis' => now()->toISOString()
            ];
            $content = json_encode($completeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $filename .= '.csv';
            $content = $this->generateCompleteCSV($commonUsers, $missingUsers);
        }
        
        \Illuminate\Support\Facades\Storage::put($filename, $content);
        
        $this->info("\n💾 Comparación completa guardada en: storage/{$filename}");
    }

    /**
     * Generar contenido CSV completo
     */
    private function generateCompleteCSV(array $commonUsers, array $missingUsers): string
    {
        $csv = "Email,Nombre,Telefono,Empresa,Tags,Estado\n";
        
        // Usuarios que están en ambos sistemas
        foreach ($commonUsers as $user) {
            $csv .= "\"{$user['email']}\",\"{$user['name']}\",\"{$user['phone']}\",\"{$user['company']}\",\"" . implode(', ', $user['tags']) . "\",En ambos sistemas\n";
        }
        
        // Usuarios faltantes
        foreach ($missingUsers as $user) {
            $csv .= "\"{$user['email']}\",\"{$user['name']}\",\"{$user['phone']}\",\"{$user['company']}\",\"" . implode(', ', $user['tags']) . "\",Faltante en Baremetrics\n";
        }
        
        return $csv;
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
