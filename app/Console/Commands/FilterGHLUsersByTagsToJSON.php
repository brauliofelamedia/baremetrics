<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FilterGHLUsersByTagsToJSON extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:filter-users-by-tags-json 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--limit=1000 : Límite máximo de usuarios a procesar}
                           {--output= : Nombre del archivo de salida (opcional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Filtra usuarios de GoHighLevel por tags específicos y guarda los resultados en un archivo JSON';

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
        $limit = (int) $this->option('limit');
        $outputFile = $this->option('output');

        $this->info('🏷️  FILTRANDO USUARIOS DE GOHIGHLEVEL POR TAGS');
        $this->info('===============================================');
        
        $this->info("🏷️  Tags a filtrar: " . implode(', ', $tags));
        $this->info("📊 Límite máximo: {$limit} usuarios");
        
        if ($outputFile) {
            $this->info("📄 Archivo de salida: {$outputFile}");
        }

        try {
            // Obtener usuarios de GoHighLevel por tags usando paginación completa
            $this->info('📥 Obteniendo usuarios de GoHighLevel por tags...');
            $allContacts = [];
            $page = 1;
            $hasMore = true;
            $totalProcessed = 0;
            
            while ($hasMore && count($allContacts) < $limit) {
                $this->info("📄 Procesando página {$page}...");
                
                $response = $this->ghlService->getContactsByTags($tags, $page);
                
                if (!$response || empty($response['contacts'])) {
                    $this->info("   No hay más contactos en la página {$page}");
                    break;
                }
                
                $contacts = $response['contacts'];
                $totalProcessed += count($contacts);
                
                // Filtrar usuarios que tengan al menos uno de los tags (OR lógico)
                $pageMatches = 0;
                foreach ($contacts as $contact) {
                    $contactTags = $contact['tags'] ?? [];
                    if (!empty(array_intersect($tags, $contactTags))) {
                        $allContacts[] = $contact;
                        $pageMatches++;
                        
                        // Verificar límite
                        if (count($allContacts) >= $limit) {
                            break 2;
                        }
                    }
                }
                
                $this->info("   Página {$page}: {$pageMatches} usuarios encontrados de {$totalProcessed} procesados");
                
                // Debug: mostrar estructura de respuesta
                $this->info("   Estructura de respuesta: " . json_encode(array_keys($response)));
                
                // Verificar si hay más páginas
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                    $this->info("   Paginación: has_more = " . ($hasMore ? 'true' : 'false'));
                    $this->info("   Paginación completa: " . json_encode($pagination));
                } else {
                    // Intentar con la siguiente página para ver si hay más datos
                    $nextPageResponse = $this->ghlService->getContactsByTags($tags, $page + 1);
                    if ($nextPageResponse && !empty($nextPageResponse['contacts'])) {
                        $hasMore = true;
                        $this->info("   No hay info de paginación, pero página siguiente tiene datos");
                    } else {
                        $hasMore = false;
                        $this->info("   No hay información de paginación y página siguiente está vacía");
                    }
                }
                
                $page++;
                
                // Pequeña pausa para evitar rate limiting
                usleep(200000); // 0.2 segundos
            }
            
            if (empty($allContacts)) {
                $this->error('❌ No se encontraron usuarios con los tags especificados');
                return 1;
            }
            
            $contacts = $allContacts;
            $totalUsers = count($contacts);
            $efficiency = $totalProcessed > 0 ? round(($totalUsers / $totalProcessed) * 100, 2) : 0;

            $this->info("✅ Se encontraron {$totalUsers} usuarios con los tags especificados");
            $this->info("📊 Total procesados: {$totalProcessed} usuarios");
            $this->info("📈 Eficiencia: {$efficiency}%");

            // Preparar datos para el JSON
            $jsonData = [
                'metadata' => [
                    'generated_at' => now()->toISOString(),
                    'tags_filtered' => $tags,
                    'total_users_found' => $totalUsers,
                    'total_users_processed' => $totalProcessed,
                    'efficiency_percentage' => $efficiency,
                    'search_limit' => $limit,
                    'generation_time_seconds' => $startTime->diffInSeconds(now())
                ],
                'users' => $contacts
            ];

            // Generar nombre de archivo si no se especifica
            if (!$outputFile) {
                $timestamp = now()->format('Y-m-d-H-i-s');
                $tagsSlug = str_replace([' ', 'á', 'é', 'í', 'ó', 'ú'], ['_', 'a', 'e', 'i', 'o', 'u'], implode('-', $tags));
                $outputFile = "ghl-users-filtered-{$tagsSlug}-{$timestamp}.json";
            }

            // Asegurar que el archivo tenga extensión .json
            if (!str_ends_with($outputFile, '.json')) {
                $outputFile .= '.json';
            }

            // Guardar archivo en storage/app
            $filepath = storage_path("app/{$outputFile}");
            $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($filepath, $jsonContent) === false) {
                $this->error("❌ Error al guardar el archivo: {$filepath}");
                return 1;
            }

            $fileSize = filesize($filepath);
            $fileSizeKB = round($fileSize / 1024, 2);

            $this->newLine();
            $this->info('✅ FILTRADO COMPLETADO EXITOSAMENTE');
            $this->info('==================================');
            $this->info("📄 Archivo guardado: {$outputFile}");
            $this->info("📁 Ruta completa: {$filepath}");
            $this->info("📊 Tamaño del archivo: {$fileSizeKB} KB");
            $this->info("👥 Total usuarios guardados: {$totalUsers}");
            $this->info("⏱️  Tiempo total: " . $startTime->diffInSeconds(now()) . " segundos");

            // Mostrar resumen de usuarios
            $this->newLine();
            $this->info('📋 RESUMEN DE USUARIOS ENCONTRADOS:');
            $this->info('==================================');
            
            $usersWithEmail = 0;
            $usersWithPhone = 0;
            $usersWithTags = 0;
            $countries = [];
            $sources = [];

            foreach ($contacts as $user) {
                if (!empty($user['email'])) {
                    $usersWithEmail++;
                }
                if (!empty($user['phone'])) {
                    $usersWithPhone++;
                }
                if (!empty($user['tags'])) {
                    $usersWithTags++;
                }
                
                if (!empty($user['country'])) {
                    $countries[$user['country']] = ($countries[$user['country']] ?? 0) + 1;
                }
                
                if (!empty($user['source'])) {
                    $sources[$user['source']] = ($sources[$user['source']] ?? 0) + 1;
                }
            }

            $this->info("📧 Usuarios con email: {$usersWithEmail}/{$totalUsers}");
            $this->info("📞 Usuarios con teléfono: {$usersWithPhone}/{$totalUsers}");
            $this->info("🏷️  Usuarios con tags: {$usersWithTags}/{$totalUsers}");

            if (!empty($countries)) {
                $this->newLine();
                $this->info('🌍 Top 5 países:');
                arsort($countries);
                $topCountries = array_slice($countries, 0, 5, true);
                foreach ($topCountries as $country => $count) {
                    $percentage = round(($count / $totalUsers) * 100, 1);
                    $this->line("   • {$country}: {$count} usuarios ({$percentage}%)");
                }
            }

            if (!empty($sources)) {
                $this->newLine();
                $this->info('📊 Top 5 fuentes:');
                arsort($sources);
                $topSources = array_slice($sources, 0, 5, true);
                foreach ($topSources as $source => $count) {
                    $percentage = round(($count / $totalUsers) * 100, 1);
                    $this->line("   • {$source}: {$count} usuarios ({$percentage}%)");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el filtrado: " . $e->getMessage());
            Log::error('Error en filtrado GHL por tags', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }
    }
}
