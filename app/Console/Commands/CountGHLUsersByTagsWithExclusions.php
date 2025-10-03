<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CountGHLUsersByTagsWithExclusions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:count-users-with-exclusions 
                           {--include-tags=creetelo_mensual,créetelo_mensual,creetelo_anual,créetelo_anual : Tags a incluir (OR)}
                           {--exclude-tags=unsubscribe : Tags a excluir}
                           {--max-pages=100 : Máximo número de páginas a procesar por tag}
                           {--save-json : Guardar resultados en archivo JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cuenta usuarios de GoHighLevel con filtro OR para tags incluidos y exclusión de tags específicos';

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
        $includeTagsString = $this->option('include-tags');
        $excludeTagsString = $this->option('exclude-tags');
        $includeTags = array_map('trim', explode(',', $includeTagsString));
        $excludeTags = array_map('trim', explode(',', $excludeTagsString));
        $maxPages = (int) $this->option('max-pages');
        $saveJson = $this->option('save-json');

        $this->info('🔢 CONTANDO USUARIOS CON FILTROS DE INCLUSIÓN Y EXCLUSIÓN');
        $this->info('=========================================================');
        
        $this->info("✅ Tags a incluir (OR): " . implode(', ', $includeTags));
        $this->info("❌ Tags a excluir: " . implode(', ', $excludeTags));
        $this->info("📄 Máximo de páginas por tag: {$maxPages}");

        try {
            $allUsers = [];
            $tagStats = [];
            
            // Consultar cada tag de inclusión por separado
            foreach ($includeTags as $tag) {
                $this->info("🔍 Procesando tag de inclusión: '{$tag}'");
                $tagResult = $this->getUsersBySingleTag($tag, $maxPages);
                
                $tagStats[$tag] = [
                    'total_users' => $tagResult['total_users'],
                    'pages_processed' => $tagResult['pages_processed'],
                    'contacts_processed' => $tagResult['contacts_processed']
                ];
                
                // Agregar usuarios únicos
                foreach ($tagResult['users'] as $user) {
                    $userId = $user['id'];
                    if (!isset($allUsers[$userId])) {
                        $allUsers[$userId] = $user;
                    }
                }
                
                $this->info("   Tag '{$tag}': {$tagResult['total_users']} usuarios únicos");
            }
            
            $totalBeforeExclusion = count($allUsers);
            $this->info("📊 Total usuarios antes de exclusión: {$totalBeforeExclusion}");
            
            // Aplicar filtro de exclusión
            $excludedCount = 0;
            $finalUsers = [];
            
            foreach ($allUsers as $userId => $user) {
                $userTags = $user['tags'] ?? [];
                
                // Verificar si el usuario tiene algún tag de exclusión
                $hasExcludedTag = false;
                foreach ($excludeTags as $excludeTag) {
                    if (in_array($excludeTag, $userTags)) {
                        $hasExcludedTag = true;
                        $excludedCount++;
                        break;
                    }
                }
                
                // Solo incluir si NO tiene tags de exclusión
                if (!$hasExcludedTag) {
                    $finalUsers[$userId] = $user;
                }
            }
            
            $totalFinalUsers = count($finalUsers);
            $duration = $startTime->diffInSeconds(now());
            
            $this->newLine();
            $this->info('✅ CONTEO COMPLETADO');
            $this->info('==================');
            $this->info("✅ Tags incluidos: " . implode(', ', $includeTags));
            $this->info("❌ Tags excluidos: " . implode(', ', $excludeTags));
            $this->info("👥 Total usuarios únicos (incluidos): {$totalBeforeExclusion}");
            $this->info("🚫 Usuarios excluidos: {$excludedCount}");
            $this->info("👥 Total usuarios únicos (final): {$totalFinalUsers}");
            $this->info("⏱️  Tiempo total: {$duration} segundos");
            
            $this->newLine();
            $this->info('📋 ESTADÍSTICAS POR TAG DE INCLUSIÓN:');
            $this->info('====================================');
            
            foreach ($tagStats as $tag => $stats) {
                $this->info("🏷️  Tag '{$tag}':");
                $this->info("   • Usuarios únicos: {$stats['total_users']}");
                $this->info("   • Páginas procesadas: {$stats['pages_processed']}");
                $this->info("   • Contactos procesados: {$stats['contacts_processed']}");
            }
            
            $this->newLine();
            $this->info('📊 RESUMEN FINAL:');
            $this->info('=================');
            $this->info("👥 Total usuarios únicos con filtros aplicados: {$totalFinalUsers}");
            
            // Calcular suma total sin deduplicar
            $sumWithoutDeduplication = array_sum(array_column($tagStats, 'total_users'));
            $this->info("📈 Suma total sin deduplicar: {$sumWithoutDeduplication}");
            
            if ($sumWithoutDeduplication > $totalBeforeExclusion) {
                $duplicates = $sumWithoutDeduplication - $totalBeforeExclusion;
                $this->info("🔄 Usuarios con múltiples tags (duplicados): {$duplicates}");
            }
            
            // Guardar en JSON si se solicita
            if ($saveJson && !empty($finalUsers)) {
                $this->saveToJson($finalUsers, $includeTags, $excludeTags, $totalFinalUsers);
            }
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error en conteo GHL con exclusiones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'include_tags' => $includeTags,
                'exclude_tags' => $excludeTags
            ]);
            return 1;
        }
    }
    
    /**
     * Obtener usuarios por un tag específico
     */
    private function getUsersBySingleTag($tag, $maxPages)
    {
        $users = [];
        $page = 1;
        $hasMore = true;
        $contactsProcessed = 0;
        
        while ($hasMore && $page <= $maxPages) {
            $response = $this->ghlService->getContactsByTags([$tag], $page);
            
            if (!$response || empty($response['contacts'])) {
                break;
            }
            
            $contacts = $response['contacts'];
            $contactsProcessed += count($contacts);
            
            foreach ($contacts as $contact) {
                $contactTags = $contact['tags'] ?? [];
                if (in_array($tag, $contactTags)) {
                    $users[] = $contact;
                }
            }
            
            // Verificar si hay más páginas
            $nextPageResponse = $this->ghlService->getContactsByTags([$tag], $page + 1);
            $hasMore = $nextPageResponse && !empty($nextPageResponse['contacts']);
            $page++;
            
            usleep(100000); // 0.1 segundos
        }
        
        return [
            'users' => $users,
            'total_users' => count($users),
            'pages_processed' => $page - 1,
            'contacts_processed' => $contactsProcessed
        ];
    }
    
    /**
     * Guardar resultados en archivo JSON
     */
    private function saveToJson($users, $includeTags, $excludeTags, $totalUsers)
    {
        $timestamp = now()->format('Y-m-d-H-i-s');
        $filename = "ghl-users-filtered-with-exclusions-{$timestamp}.json";
        $filepath = storage_path("app/{$filename}");

        $jsonData = [
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'include_tags' => $includeTags,
                'exclude_tags' => $excludeTags,
                'total_users' => $totalUsers,
                'description' => 'Usuarios de GoHighLevel filtrados por tags de inclusión (OR) y exclusión'
            ],
            'users' => array_values($users)
        ];

        $jsonContent = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($filepath, $jsonContent) !== false) {
            $fileSize = filesize($filepath);
            $fileSizeKB = round($fileSize / 1024, 2);
            
            $this->info("📄 Archivo JSON guardado: {$filename}");
            $this->info("📁 Ruta: {$filepath}");
            $this->info("📊 Tamaño: {$fileSizeKB} KB");
        } else {
            $this->error("❌ Error al guardar archivo JSON");
        }
    }
}
