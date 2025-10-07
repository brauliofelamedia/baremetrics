<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class ShowCompleteComparison extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * ghl:show-complete-comparison --tags=creetelo_mensual,créetelo_mensual,creetelo_anual,créetelo_anual --exclude-tags=ubsubscribe
     */
    protected $signature = 'ghl:show-complete-comparison 
                            {--tags=creetelo_mensual,creetelo_anual : Tags de GHL separados por coma}
                            {--exclude-tags=unsubscribe : Tags a excluir separados por coma}
                            {--limit=50 : Límite de usuarios a procesar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Muestra resumen completo de usuarios GHL vs Baremetrics (quién está y quién no)';

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
        $this->info('🔍 Analizando usuarios GHL vs Baremetrics...');
        
        // Obtener parámetros
        $tagsString = $this->option('tags');
        $excludeTagsString = $this->option('exclude-tags');
        $limit = (int) $this->option('limit');

        // Convertir strings a arrays
        $tags = array_map('trim', explode(',', $tagsString));
        $excludeTags = array_map('trim', explode(',', $excludeTagsString));

        $this->info("📋 Configuración:");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line("   • Límite: {$limit} usuarios");

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

            // 3. Analizar usuarios
            $this->analyzeUsers($ghlUsers, $baremetricsEmails);

        } catch (\Exception $e) {
            $this->error("❌ Error durante el análisis: " . $e->getMessage());
            Log::error('Error en análisis completo', [
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
            
            $maxPages = 200; // Límite de páginas para evitar bucles infinitos
            
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
                    $this->line("       • Progreso: {$processedCount} contactos procesados, " . count($allUsers) . " usuarios válidos encontrados (página {$page})");
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
     * Analizar usuarios y mostrar resumen completo
     */
    private function analyzeUsers(array $ghlUsers, array $baremetricsEmails): void
    {
        $this->info("\n🔄 Analizando usuarios...");
        
        $commonUsers = [];
        $missingUsers = [];
        
        foreach ($ghlUsers as $user) {
            if (in_array($user['email'], $baremetricsEmails)) {
                $commonUsers[] = $user;
            } else {
                $missingUsers[] = $user;
            }
        }

        // Mostrar resumen completo
        $this->showCompleteSummary($ghlUsers, $baremetricsEmails, $commonUsers, $missingUsers);
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
            
            // Estadísticas por tag para usuarios comunes
            $this->showTagStatistics($commonUsers, "USUARIOS SINCRONIZADOS");
        }

        // Mostrar usuarios faltantes
        if (!empty($missingUsers)) {
            $this->warn("\n⚠️ USUARIOS DE GHL FALTANTES EN BAREMETRICS:");
            $this->line("=============================================");
            
            foreach ($missingUsers as $user) {
                $this->line("   • {$user['email']} - {$user['name']} - Tags: " . implode(', ', $user['tags']));
            }
            
            // Estadísticas por tag para usuarios faltantes
            $this->showTagStatistics($missingUsers, "USUARIOS FALTANTES");
        }

        // Resumen final
        $this->info("\n🎯 RESUMEN FINAL:");
        $this->line("==================");
        
        if (count($missingUsers) === 0) {
            $this->info("✅ ¡Perfecto! Todos los usuarios de GHL están sincronizados en Baremetrics");
        } else {
            $this->warn("⚠️ Hay " . count($missingUsers) . " usuarios de GHL que necesitan ser importados a Baremetrics");
        }
        
        if (count($commonUsers) > 0) {
            $this->info("✅ " . count($commonUsers) . " usuarios ya están sincronizados correctamente");
        }
    }

    /**
     * Mostrar estadísticas por tag
     */
    private function showTagStatistics(array $users, string $title): void
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
     * Validar si un email es válido
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
