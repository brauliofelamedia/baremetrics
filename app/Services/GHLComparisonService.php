<?php

namespace App\Services;

use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GHLComparisonService
{
    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Procesar comparación completa
     */
    public function processComparison(ComparisonRecord $comparison)
    {
        try {
            $comparison->update(['status' => 'processing']);
            $comparison->updateProgress('Iniciando procesamiento...', 0);

            // Paso 1: Leer usuarios del CSV
            $comparison->updateProgress('Leyendo archivo CSV...', 5);
            $ghlUsers = $this->readCSVUsers($comparison->csv_file_path);
            
            if (empty($ghlUsers)) {
                throw new \Exception('No se encontraron usuarios válidos en el CSV');
            }

            $totalGHLUsers = count($ghlUsers);
            $comparison->updateProgress('CSV leído exitosamente', 10, [
                'total_ghl_users' => $totalGHLUsers,
                'total_rows_processed' => $totalGHLUsers
            ]);

            // Paso 2: Obtener usuarios de Baremetrics
            $comparison->updateProgress('Obteniendo usuarios de Baremetrics...', 15);
            $baremetricsUsers = $this->getBaremetricsUsers($comparison);
            $totalBaremetricsUsers = count($baremetricsUsers);
            $comparison->updateProgress('Usuarios de Baremetrics obtenidos', 25, [
                'total_baremetrics_users' => $totalBaremetricsUsers,
                'baremetrics_users_fetched' => $totalBaremetricsUsers
            ]);
            
            // Paso 3: Comparar usuarios
            $comparison->updateProgress('Realizando comparaciones...', 30);
            $comparisonResult = $this->compareUsers($ghlUsers, $baremetricsUsers, $comparison);
            
            // Paso 4: Guardar resultados
            $comparison->updateProgress('Guardando resultados...', 90);
            $this->saveComparisonResults($comparison, $comparisonResult);
            
            // Paso 5: Completar
            $comparison->updateProgress('Procesamiento completado', 100, [
                'status' => 'completed',
                'processed_at' => now()
            ]);

            Log::info('Comparison completed successfully', [
                'comparison_id' => $comparison->id,
                'total_ghl' => count($ghlUsers),
                'total_baremetrics' => count($baremetricsUsers),
                'found' => count($comparisonResult['found']),
                'missing' => count($comparisonResult['missing'])
            ]);

        } catch (\Exception $e) {
            $comparison->updateProgress('Error en procesamiento', 0, [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            
            Log::error('Comparison failed', [
                'comparison_id' => $comparison->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Leer usuarios del CSV
     */
    private function readCSVUsers($filePath)
    {
        $users = [];
        
        // Si la ruta ya es absoluta, usarla directamente
        if (file_exists($filePath)) {
            $fullPath = $filePath;
        } else {
            // Si no, intentar con el disco public
            $fullPath = Storage::disk('public')->path($filePath);
        }

        if (!file_exists($fullPath)) {
            throw new \Exception('Archivo CSV no encontrado: ' . $filePath);
        }

        if (($handle = fopen($fullPath, "r")) !== FALSE) {
            // Leer headers
            $headers = fgetcsv($handle, 1000, ",");
            
            if (!$headers) {
                throw new \Exception('No se pudieron leer los headers del CSV');
            }

            // Mapear columnas
            $columnMap = $this->mapColumns($headers);

            // Leer datos
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < count($headers)) {
                    continue; // Saltar filas incompletas
                }

                $user = $this->parseUserFromCSV($data, $columnMap);
                
                if ($user && $this->isValidEmail($user['email'])) {
                    $users[] = $user;
                }
            }
            
            fclose($handle);
        }

        return $users;
    }

    /**
     * Mapear columnas del CSV
     */
    private function mapColumns($headers)
    {
        $map = [];
        
        foreach ($headers as $index => $header) {
            $header = trim($header);
            
            switch (strtolower($header)) {
                case 'email':
                    $map['email'] = $index;
                    break;
                case 'first name':
                    $map['first_name'] = $index;
                    break;
                case 'last name':
                    $map['last_name'] = $index;
                    break;
                case 'phone':
                    $map['phone'] = $index;
                    break;
                case 'company name':
                    $map['company_name'] = $index;
                    break;
                case 'business name':
                    $map['business_name'] = $index;
                    break;
                case 'tags':
                    $map['tags'] = $index;
                    break;
                case 'created':
                    $map['created'] = $index;
                    break;
                case 'last activity':
                    $map['last_activity'] = $index;
                    break;
            }
        }

        return $map;
    }

    /**
     * Parsear usuario del CSV
     */
    private function parseUserFromCSV($data, $columnMap)
    {
        $firstName = isset($columnMap['first_name']) ? trim($data[$columnMap['first_name']]) : '';
        $lastName = isset($columnMap['last_name']) ? trim($data[$columnMap['last_name']]) : '';
        $name = trim($firstName . ' ' . $lastName);
        if (empty($name)) {
            $name = 'Sin nombre';
        }

        return [
            'name' => $name,
            'email' => isset($columnMap['email']) ? strtolower(trim($data[$columnMap['email']])) : '',
            'phone' => isset($columnMap['phone']) ? trim($data[$columnMap['phone']]) : '',
            'company' => isset($columnMap['company_name']) ? trim($data[$columnMap['company_name']]) : 
                        (isset($columnMap['business_name']) ? trim($data[$columnMap['business_name']]) : ''),
            'tags' => isset($columnMap['tags']) ? trim($data[$columnMap['tags']]) : '',
            'created_date' => isset($columnMap['created']) ? $this->parseDate($data[$columnMap['created']]) : null,
            'last_activity' => isset($columnMap['last_activity']) ? $this->parseDate($data[$columnMap['last_activity']]) : null,
        ];
    }

    /**
     * Parsear fecha
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtener usuarios de Baremetrics de TODOS los sources
     */
    private function getBaremetricsUsers(ComparisonRecord $comparison)
    {
        try {
            // Configurar para producción
            config(['services.baremetrics.environment' => 'production']);
            $this->baremetricsService->reinitializeConfiguration();
            
            $allUsers = [];
            $sourcesChecked = 0;

            // Paso 1: Obtener todos los sources
            $comparison->updateProgress('Obteniendo sources de Baremetrics...', 15);
            $sourcesResponse = $this->baremetricsService->getSources();
            
            if (!$sourcesResponse || !isset($sourcesResponse['sources'])) {
                throw new \Exception('No se pudieron obtener los sources de Baremetrics');
            }

            $sources = $sourcesResponse['sources'];
            $totalSources = count($sources);
            
            $comparison->updateProgress("Encontrados {$totalSources} sources, verificando usuarios...", 20);

            // Paso 2: Obtener usuarios de cada source
            foreach ($sources as $source) {
                $sourceId = $source['id'];
                $provider = $source['provider'] ?? 'unknown';
                $sourcesChecked++;
                
                // Para sources de Stripe, hacer búsqueda específica por email en lugar de obtener todos los usuarios
                if ($provider === 'stripe') {
                    Log::info("Procesando source de Stripe con búsqueda específica", [
                        'source_id' => $sourceId,
                        'provider' => $provider
                    ]);
                    
                    $comparison->updateProgress(
                        "Procesando source {$sourcesChecked}/{$totalSources} ({$provider}) - búsqueda específica...", 
                        20 + (($sourcesChecked / $totalSources) * 5),
                        ['sources_checked' => $sourcesChecked, 'stripe_search_mode' => true]
                    );
                    
                    // Para Stripe, solo procesamos usuarios específicos del CSV
                    // Esto se hará en el método compareUsers
                    continue;
                }
                
                $comparison->updateProgress(
                    "Verificando source {$sourcesChecked}/{$totalSources} ({$provider})...", 
                    20 + (($sourcesChecked / $totalSources) * 5), // 20% a 25%
                    ['sources_checked' => $sourcesChecked]
                );

                try {
                    $page = 1;
                    $hasMore = true;
                    $sourceUsers = [];
                    $maxPages = 50; // Límite de páginas por source para evitar timeouts
                    $maxUsersPerSource = 5000; // Límite de usuarios por source

                    while ($hasMore && $page <= $maxPages) {
                        $customersResponse = $this->baremetricsService->getCustomers($sourceId, '', $page);
                        
                        if (!$customersResponse || !isset($customersResponse['customers'])) {
                            break;
                        }

                        $customers = $customersResponse['customers'];
                        
                        if (empty($customers)) {
                            break;
                        }

                        foreach ($customers as $customer) {
                            $email = strtolower(trim($customer['email'] ?? ''));
                            if (!empty($email)) {
                                // Evitar duplicados por email, pero mantener info del source
                                if (!isset($allUsers[$email])) {
                                    $allUsers[$email] = [
                                        'email' => $email,
                                        'id' => $customer['oid'] ?? $customer['id'] ?? null,
                                        'name' => $customer['name'] ?? '',
                                        'sources' => []
                                    ];
                                }
                                
                                // Agregar información del source actual
                                $allUsers[$email]['sources'][] = [
                                    'source_id' => $sourceId,
                                    'provider' => $provider,
                                    'customer_oid' => $customer['oid'] ?? $customer['id'] ?? null
                                ];
                            }
                        }

                        // Verificar límite de usuarios por source
                        if (count($allUsers) > $maxUsersPerSource) {
                            Log::warning('Límite de usuarios alcanzado para source', [
                                'source_id' => $sourceId,
                                'provider' => $provider,
                                'max_users' => $maxUsersPerSource
                            ]);
                            break;
                        }

                        // Verificar si hay más páginas
                        $hasMore = isset($customersResponse['meta']['pagination']) && 
                                  ($customersResponse['meta']['pagination']['has_more'] ?? false);
                        $page++;

                        // Pausa más larga entre requests para sources grandes
                        usleep(100000); // 100ms
                    }

                    // Log del progreso del source
                    Log::info('Source procesado', [
                        'source_id' => $sourceId,
                        'provider' => $provider,
                        'pages_processed' => $page - 1,
                        'users_found' => count($allUsers)
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Error obteniendo usuarios del source', [
                        'source_id' => $sourceId,
                        'provider' => $provider,
                        'error' => $e->getMessage()
                    ]);
                    // Continuar con el siguiente source
                }
            }

            // Convertir array asociativo a array indexado
            $usersArray = array_values($allUsers);
            
            $comparison->updateProgress('Usuarios de Baremetrics obtenidos de todos los sources', 25, [
                'total_baremetrics_users' => count($usersArray),
                'baremetrics_users_fetched' => count($usersArray),
                'sources_checked' => $sourcesChecked
            ]);

            return $usersArray;

        } catch (\Exception $e) {
            Log::error('Error getting Baremetrics users from all sources', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Comparar usuarios
     */
    private function compareUsers($ghlUsers, $baremetricsUsers, ComparisonRecord $comparison)
    {
        $found = [];
        $missing = [];
        $foundInOtherSources = [];
        
        // Crear mapa de emails de Baremetrics para búsqueda rápida
        $baremetricsMap = [];
        foreach ($baremetricsUsers as $user) {
            $baremetricsMap[$user['email']] = $user;
        }

        // Obtener sources de Stripe para búsqueda específica
        $stripeSources = $this->getStripeSources();

        $totalUsers = count($ghlUsers);
        $processedCount = 0;

        foreach ($ghlUsers as $ghlUser) {
            $email = $ghlUser['email'];
            $processedCount++;
            
            $userFound = false;
            
            // Primero verificar en usuarios ya obtenidos
            if (isset($baremetricsMap[$email])) {
                $baremetricsUser = $baremetricsMap[$email];
                
                // Verificar si el usuario está en el source manual o en otros sources
                $isInManualSource = false;
                $otherSources = [];
                
                foreach ($baremetricsUser['sources'] as $source) {
                    if ($source['source_id'] === 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8') { // Manual source ID
                        $isInManualSource = true;
                    } else {
                        $otherSources[] = $source;
                    }
                }
                
                if ($isInManualSource) {
                    // Usuario encontrado en source manual - está correctamente migrado
                    $found[] = [
                        'ghl_user' => $ghlUser,
                        'baremetrics_user' => $baremetricsUser,
                        'status' => 'in_manual_source'
                    ];
                } else {
                    // Usuario encontrado en otros sources - necesita migración manual
                    $foundInOtherSources[] = [
                        'ghl_user' => $ghlUser,
                        'baremetrics_user' => $baremetricsUser,
                        'status' => 'in_other_source',
                        'sources' => $otherSources
                    ];
                }
                $userFound = true;
            } else {
                // Si no se encontró en usuarios obtenidos, buscar específicamente en Stripe
                foreach ($stripeSources as $stripeSource) {
                    $customers = $this->baremetricsService->getCustomers($stripeSource['id'], $email);
                    
                    if ($customers && isset($customers['customers'])) {
                        foreach ($customers['customers'] as $customer) {
                            if (strtolower($customer['email']) === strtolower($email)) {
                                // Usuario encontrado en Stripe
                                $foundInOtherSources[] = [
                                    'ghl_user' => $ghlUser,
                                    'baremetrics_user' => [
                                        'email' => $email,
                                        'id' => $customer['oid'] ?? $customer['id'] ?? null,
                                        'name' => $customer['name'] ?? '',
                                        'sources' => [[
                                            'source_id' => $stripeSource['id'],
                                            'provider' => 'stripe',
                                            'customer_oid' => $customer['oid'] ?? $customer['id'] ?? null
                                        ]]
                                    ],
                                    'status' => 'in_other_source',
                                    'sources' => [[
                                        'source_id' => $stripeSource['id'],
                                        'provider' => 'stripe',
                                        'customer_oid' => $customer['oid'] ?? $customer['id'] ?? null
                                    ]]
                                ];
                                $userFound = true;
                                break 2; // Salir de ambos bucles
                            }
                        }
                    }
                }
            }
            
            // Si no se encontró en ningún lado, es realmente faltante
            if (!$userFound) {
                $missing[] = $ghlUser;
            }

            // Actualizar progreso cada 50 usuarios o al final
            if ($processedCount % 50 == 0 || $processedCount == $totalUsers) {
                $progressPercentage = 30 + (($processedCount / $totalUsers) * 60); // 30% a 90%
                $comparison->updateProgress(
                    "Comparando usuarios... ({$processedCount}/{$totalUsers})",
                    $progressPercentage,
                    [
                        'ghl_users_processed' => $processedCount,
                        'comparisons_made' => $processedCount,
                        'users_found_count' => count($found),
                        'users_missing_count' => count($missing),
                        'users_in_other_sources_count' => count($foundInOtherSources)
                    ]
                );
            }
        }

        return [
            'found' => $found,
            'missing' => $missing,
            'found_in_other_sources' => $foundInOtherSources,
            'total_ghl' => count($ghlUsers),
            'total_baremetrics' => count($baremetricsUsers),
            'found_count' => count($found),
            'missing_count' => count($missing),
            'found_in_other_sources_count' => count($foundInOtherSources),
        ];
    }

    /**
     * Guardar resultados de la comparación
     */
    private function saveComparisonResults(ComparisonRecord $comparison, $results)
    {
        // Actualizar estadísticas principales
        $syncPercentage = $results['total_ghl'] > 0 ? 
            round(($results['found_count'] / $results['total_ghl']) * 100, 2) : 0;

        $comparison->update([
            'total_ghl_users' => $results['total_ghl'],
            'total_baremetrics_users' => $results['total_baremetrics'],
            'users_found_in_baremetrics' => $results['found_count'],
            'users_missing_from_baremetrics' => $results['missing_count'],
            'sync_percentage' => $syncPercentage,
            'comparison_data' => $results['found'],
            'missing_users_data' => $results['missing'],
            'found_in_other_sources_data' => $results['found_in_other_sources'],
        ]);

        // Guardar usuarios faltantes en la base de datos
        foreach ($results['missing'] as $user) {
            MissingUser::create([
                'comparison_id' => $comparison->id,
                'email' => $user['email'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'company' => $user['company'],
                'tags' => $user['tags'],
                'created_date' => $user['created_date'],
                'last_activity' => $user['last_activity'],
                'import_status' => 'pending',
            ]);
        }

        // Guardar usuarios encontrados en otros sources
        foreach ($results['found_in_other_sources'] as $foundUser) {
            $user = $foundUser['ghl_user'];
            $sources = $foundUser['sources'];
            
            MissingUser::create([
                'comparison_id' => $comparison->id,
                'email' => $user['email'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'company' => $user['company'],
                'tags' => $user['tags'],
                'created_date' => $user['created_date'],
                'last_activity' => $user['last_activity'],
                'import_status' => 'found_in_other_source',
                'baremetrics_customer_id' => $foundUser['baremetrics_user']['id'],
                'import_notes' => 'Usuario encontrado en sources: ' . implode(', ', array_column($sources, 'provider')),
            ]);
        }
    }

    /**
     * Obtener sources de Stripe
     */
    private function getStripeSources()
    {
        try {
            $sourcesResponse = $this->baremetricsService->getSources();
            $sources = $sourcesResponse['sources'] ?? [];
            
            return array_filter($sources, function($source) {
                return ($source['provider'] ?? '') === 'stripe';
            });
        } catch (\Exception $e) {
            Log::error('Error obteniendo sources de Stripe', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Verificar si el email es válido
     */
    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
