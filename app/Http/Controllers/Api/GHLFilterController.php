<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GHLFilterController extends Controller
{
    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        $this->ghlService = $ghlService;
    }

    /**
     * Filtrar usuarios de GoHighLevel por tags con inclusión y exclusión
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterUsersByTags(Request $request)
    {
        try {
            // Validar parámetros de entrada
            $validator = Validator::make($request->all(), [
                'tags' => 'required|string',
                'exclude_tags' => 'nullable|string',
                'max_pages' => 'nullable|integer|min:1|max:200',
                'limit' => 'nullable|integer|min:1|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parámetros de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Parsear parámetros
            $includeTags = array_map('trim', explode(',', $request->input('tags')));
            $excludeTags = $request->has('exclude_tags') ? 
                array_map('trim', explode(',', $request->input('exclude_tags'))) : [];
            $maxPages = $request->input('max_pages', 100);
            $limit = $request->input('limit', 5000);

            // Filtrar tags vacíos
            $includeTags = array_filter($includeTags);
            $excludeTags = array_filter($excludeTags);

            if (empty($includeTags)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar al menos un tag para incluir'
                ], 400);
            }

            // Procesar filtrado
            $result = $this->processFiltering($includeTags, $excludeTags, $maxPages, $limit);

            return response()->json([
                'success' => true,
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en GHLFilterController', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar el filtrado de usuarios
     */
    private function processFiltering($includeTags, $excludeTags, $maxPages, $limit)
    {
        $startTime = now();
        $allUsers = [];
        $tagStats = [];
        
        // Consultar cada tag de inclusión por separado
        foreach ($includeTags as $tag) {
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
        }
        
        $totalBeforeExclusion = count($allUsers);
        
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
                
                // Aplicar límite si se especifica
                if (count($finalUsers) >= $limit) {
                    break;
                }
            }
        }
        
        $totalFinalUsers = count($finalUsers);
        $duration = $startTime->diffInSeconds(now());
        
        // Calcular estadísticas adicionales
        $sumWithoutDeduplication = array_sum(array_column($tagStats, 'total_users'));
        $duplicates = $sumWithoutDeduplication - $totalBeforeExclusion;
        
        return [
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'include_tags' => $includeTags,
                'exclude_tags' => $excludeTags,
                'total_users_before_exclusion' => $totalBeforeExclusion,
                'total_users_excluded' => $excludedCount,
                'total_users_final' => $totalFinalUsers,
                'sum_without_deduplication' => $sumWithoutDeduplication,
                'duplicates_removed' => $duplicates,
                'processing_time_seconds' => $duration,
                'max_pages_per_tag' => $maxPages,
                'limit_applied' => $limit
            ],
            'tag_statistics' => $tagStats,
            'users' => array_values($finalUsers)
        ];
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
            
            usleep(100000); // 0.1 segundos para evitar rate limiting
        }
        
        return [
            'users' => $users,
            'total_users' => count($users),
            'pages_processed' => $page - 1,
            'contacts_processed' => $contactsProcessed
        ];
    }

    /**
     * Obtener estadísticas de tags disponibles
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTagStatistics(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tags' => 'required|string',
                'max_pages' => 'nullable|integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parámetros de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $tags = array_map('trim', explode(',', $request->input('tags')));
            $maxPages = $request->input('max_pages', 10);
            $tags = array_filter($tags);

            $statistics = [];
            
            foreach ($tags as $tag) {
                $tagResult = $this->getUsersBySingleTag($tag, $maxPages);
                $statistics[$tag] = [
                    'total_users' => $tagResult['total_users'],
                    'pages_processed' => $tagResult['pages_processed'],
                    'contacts_processed' => $tagResult['contacts_processed']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metadata' => [
                        'generated_at' => now()->toISOString(),
                        'tags_analyzed' => $tags,
                        'max_pages_per_tag' => $maxPages
                    ],
                    'tag_statistics' => $statistics
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en getTagStatistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
