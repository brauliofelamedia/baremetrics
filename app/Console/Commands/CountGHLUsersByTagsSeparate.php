<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CountGHLUsersByTagsSeparate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:count-users-by-tags-separate 
                           {--tags=creetelo_anual,creetelo_mensual,créetelo_anual,créetelo_mensual : Tags separados por comas}
                           {--max-pages=100 : Máximo número de páginas a procesar por tag}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cuenta el total de usuarios de GoHighLevel consultando cada tag por separado y luego deduplicando (filtro OR real)';

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
        $maxPages = (int) $this->option('max-pages');

        $this->info('🔢 CONTANDO USUARIOS DE GOHIGHLEVEL POR TAGS (CONSULTA SEPARADA)');
        $this->info('================================================================');
        
        $this->info("🏷️  Tags a buscar: " . implode(', ', $tags));
        $this->info("📄 Máximo de páginas por tag: {$maxPages}");

        try {
            $allUserIds = [];
            $tagStats = [];
            
            // Consultar cada tag por separado
            foreach ($tags as $tag) {
                $this->info("🔍 Procesando tag: '{$tag}'");
                $tagResult = $this->getUsersBySingleTag($tag, $maxPages);
                
                $tagStats[$tag] = [
                    'total_users' => $tagResult['total_users'],
                    'pages_processed' => $tagResult['pages_processed'],
                    'contacts_processed' => $tagResult['contacts_processed']
                ];
                
                // Agregar IDs únicos
                foreach ($tagResult['user_ids'] as $userId) {
                    if (!in_array($userId, $allUserIds)) {
                        $allUserIds[] = $userId;
                    }
                }
                
                $this->info("   Tag '{$tag}': {$tagResult['total_users']} usuarios únicos");
            }
            
            $totalUniqueUsers = count($allUserIds);
            $duration = $startTime->diffInSeconds(now());
            
            $this->newLine();
            $this->info('✅ CONTEO COMPLETADO');
            $this->info('==================');
            $this->info("🏷️  Tags procesados: " . implode(', ', $tags));
            $this->info("👥 Total usuarios únicos (OR): {$totalUniqueUsers}");
            $this->info("⏱️  Tiempo total: {$duration} segundos");
            
            $this->newLine();
            $this->info('📋 ESTADÍSTICAS POR TAG:');
            $this->info('========================');
            
            foreach ($tagStats as $tag => $stats) {
                $this->info("🏷️  Tag '{$tag}':");
                $this->info("   • Usuarios únicos: {$stats['total_users']}");
                $this->info("   • Páginas procesadas: {$stats['pages_processed']}");
                $this->info("   • Contactos procesados: {$stats['contacts_processed']}");
            }
            
            $this->newLine();
            $this->info('📊 RESUMEN FINAL:');
            $this->info('=================');
            $this->info("👥 Total usuarios únicos con al menos un tag: {$totalUniqueUsers}");
            
            // Calcular suma total sin deduplicar
            $sumWithoutDeduplication = array_sum(array_column($tagStats, 'total_users'));
            $this->info("📈 Suma total sin deduplicar: {$sumWithoutDeduplication}");
            
            if ($sumWithoutDeduplication > $totalUniqueUsers) {
                $duplicates = $sumWithoutDeduplication - $totalUniqueUsers;
                $this->info("🔄 Usuarios con múltiples tags (duplicados): {$duplicates}");
            }
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante el conteo: " . $e->getMessage());
            Log::error('Error en conteo GHL por tags separados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tags' => $tags
            ]);
            return 1;
        }
    }
    
    /**
     * Obtener usuarios por un tag específico
     */
    private function getUsersBySingleTag($tag, $maxPages)
    {
        $userIds = [];
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
                    $userIds[] = $contact['id'];
                }
            }
            
            // Verificar si hay más páginas
            $nextPageResponse = $this->ghlService->getContactsByTags([$tag], $page + 1);
            $hasMore = $nextPageResponse && !empty($nextPageResponse['contacts']);
            $page++;
            
            usleep(100000); // 0.1 segundos
        }
        
        return [
            'user_ids' => $userIds,
            'total_users' => count($userIds),
            'pages_processed' => $page - 1,
            'contacts_processed' => $contactsProcessed
        ];
    }
}
