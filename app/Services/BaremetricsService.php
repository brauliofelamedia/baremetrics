<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaremetricsService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->initializeConfiguration();
    }

    /**
     * Initialize configuration based on current environment setting
     */
    private function initializeConfiguration()
    {
        $environment = config('services.baremetrics.environment', 'sandbox');
        
        if ($environment === 'production') {
            $this->apiKey = config('services.baremetrics.live_key');
            $this->baseUrl = config('services.baremetrics.production_url');
        } else {
            $this->apiKey = config('services.baremetrics.sandbox_key');
            $this->baseUrl = config('services.baremetrics.sandbox_url');
        }
    }

    /**
     * Reinitialize configuration (useful when config changes after instantiation)
     */
    public function reinitializeConfiguration()
    {
        $this->initializeConfiguration();
    }

    /**
     * Create a new customer in Baremetrics (simplified version)
     *
     * @param array $customerData
     * @return string|null Customer ID
     */
    public function createCustomerSimple(array $customerData): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/customers', $customerData);

            if ($response->successful()) {
                $data = $response->json();
                return $data['customer']['id'] ?? null;
            }

            Log::error('Baremetrics Create Customer Error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'customer_data' => $customerData,
            ]);

            throw new \Exception('Error creating customer: ' . $response->status());

        } catch (\Exception $e) {
            Log::error('Baremetrics Create Customer Exception', [
                'error' => $e->getMessage(),
                'customer_data' => $customerData,
            ]);
            throw $e;
        }
    }

    /**
     * Get account information from Baremetrics
     *
     * @return array|null
     */
    public function getAccount(): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/account');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get users from Baremetrics
     *
     * @return array|null
     */
    public function getUsers(): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/users');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error - Users', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Users', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get sources from Baremetrics
     *
     * @return array|null
     */
    public function getSources(): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/sources');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error - Sources', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Sources', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get plans from Baremetrics for a specific source
     *
     * @param string $sourceId The source ID to get plans for
     * @param int|null $page Page number for pagination (optional)
     * @param int|null $perPage Number of items per page (optional)
     * @return array|null
     */
    public function getPlans(string $sourceId, ?int $page = null, ?int $perPage = null): ?array
    {
        try {
            $queryParams = [];
            
            if ($page !== null) {
                $queryParams['page'] = $page;
            }
            
            if ($perPage !== null) {
                $queryParams['per_page'] = $perPage;
            }
            
            $url = $this->baseUrl . '/' . $sourceId . '/plans';
            
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error - Plans', [
                'status' => $response->status(),
                'response' => $response->body(),
                'source_id' => $sourceId,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Plans', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source_id' => $sourceId,
            ]);

            return null;
        }
    }

    /**
     * Create a new customer in Baremetrics for a specific source
     *
     * @param array $customerData Associative array with customer details (name, email, company, notes, oid)
     * @param string $sourceId The source ID to create the customer under
     * @return array|null
     */
    public function createCustomer(array $customerData, string $sourceId): ?array
    {
        try {
            $url = $this->baseUrl . '/' . $sourceId . '/customers';

            $body = [
                'name' => $customerData['name'] ?? null,
                'email' => $customerData['email'] ?? null,
                'company' => $customerData['company'] ?? null,
                'notes' => $customerData['notes'] ?? null,
                'oid' => $customerData['oid'] ?? 'cust_' . uniqid(),
            ];

            // Agregar campos personalizados si existen
            if (isset($customerData['properties']) && is_array($customerData['properties'])) {
                $body['properties'] = $customerData['properties'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, $body);

            if ($response->successful()) {
                Log::info('Baremetrics Customer Created Successfully', [
                    'source_id' => $sourceId,
                    'customer_data' => $customerData,
                    'response' => $response->json()
                ]);
                return $response->json();
            }

            Log::error('Baremetrics API Error - Create customer', [
                'status' => $response->status(),
                'response' => $response->body(),
                'source_id' => $sourceId,
                'customer_data' => $customerData,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Create customer', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source_id' => $sourceId,
                'customer_data' => $customerData,
            ]);

            return null;
        }
    }

    /**
     * Get customers from Baremetrics for a specific source
     *
     * @param string $sourceId The source ID to get customers for
     * @param int|null $page Page number for pagination (optional)
     * @param int|null $perPage Number of items per page (optional)
     * @return array|null
     */
    public function getCustomers(string $sourceId, string $search = '',$page = 0): ?array
    {
        try {
            $allCustomers = [];
            // Si se pasa un sourceId, solo busca para ese source
            if ($sourceId) {
                // Si se usa search, no agregar paginación (la API filtra directamente)
                if (!empty($search)) {
                    $url = $this->baseUrl . '/' . $sourceId . '/customers?search=' . urlencode($search) . '&sort=created&order=asc';
                } else {
                    $url = $this->baseUrl . '/' . $sourceId . '/customers?sort=created&page=' . $page . '&order=asc&per_page=100';
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Baremetrics API Error - Customers', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'source_id' => $sourceId,
                ]);

                return null;
            }

            return ['customers' => $allCustomers];

        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Customers', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function getCustomersAll(string $sourceId, $page = 0): ?array
    {
        try {
            $allCustomers = [];

            // Si se pasa un sourceId, solo busca para ese source
            if ($sourceId) {
                $url = $this->baseUrl . '/' . $sourceId . '/customers?page=' . $page . '&per_page=200';

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->get($url);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Baremetrics API Error - Customers', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'source_id' => $sourceId,
                ]);

                return null;
            }
            
            return ['customers' => $allCustomers];

        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Customers', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get subscriptions from Baremetrics for a specific source
     *
     * @param string $sourceId The source ID to get subscriptions for
     * @param int|null $page Page number for pagination (optional)
     * @param int|null $perPage Number of items per page (optional)
     * @return array|null
     */
    public function getSubscriptions(string $sourceId, ?int $page = null, ?int $perPage = null): ?array
    {
        try {
            $queryParams = [];
            
            if ($page !== null) {
                $queryParams['page'] = $page;
            }
            
            if ($perPage !== null) {
                $queryParams['per_page'] = $perPage;
            }
            
            $url = $this->baseUrl . '/' . $sourceId . '/subscriptions';
            
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error - Subscriptions', [
                'status' => $response->status(),
                'response' => $response->body(),
                'source_id' => $sourceId,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Subscriptions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source_id' => $sourceId,
            ]);

            return null;
        }
    }

    /**
     * Get customers by email from Baremetrics
     *
     * @param string $email
     * @return array|null
     */
    public function getCustomersByEmail($email)
    {
        try {
            $sources = $this->getSources();

            if (!$sources) {
                return null;
            }

            // Normalizar respuesta de fuentes
            $sourcesNew = [];
            if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
                $sourcesNew = $sources['sources'];
            } elseif (is_array($sources)) {
                $sourcesNew = $sources;
            }

            // Buscar en todas las fuentes disponibles usando el parámetro search de la API
            $sourceIds = array_values(array_filter(array_column($sourcesNew, 'id'), function ($id) {
                return !empty($id);
            }));

            if (empty($sourceIds)) {
                return null;
            }

            // Buscar en cada fuente usando el parámetro search (la API filtra directamente)
            foreach ($sourceIds as $sourceId) {
                $response = $this->getCustomers($sourceId, $email, 1);

                if (!$response) {
                    continue;
                }

                $customers = [];
                if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }

                // Buscar por email en los resultados filtrados
                foreach ($customers as $customer) {
                    if (isset($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
                        return [$customer]; // Devolver array con el cliente encontrado
                    }
                }

                // Pequeña pausa entre requests para no sobrecargar la API
                usleep(100000); // 100ms
            }

            return null; // No encontrado

        } catch (\Exception $e) {
            Log::error('Error obteniendo cliente por email de Baremetrics', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }    /**
     * Update customer attributes in Baremetrics
     * 
     * @param string $customerId The customer ID in Baremetrics
     * @param array $ghlData The data from GoHighLevel containing customer attributes
     * @return array|null Response from Baremetrics API or null if failed
     */
    public function updateCustomerAttributes($customerId, $ghlData)
    {
        $url = $this->baseUrl . '/attributes';

        // Log received data for debugging
        Log::debug('Updating customer attributes', [
            'customer_id' => $customerId,
            'received_data' => $ghlData,
        ]);

        // Map of field IDs to ghlData keys
        $mapping = [
            '727708655' => 'relationship_status',
            '727708792' => 'community_location',
            '727706634' => 'country',
            '727707546' => 'engagement_score',
            '727708656' => 'has_kids',
            '727707002' => 'state',
            '727709283' => 'location',
            '727708657' => 'zodiac_sign',
            '750414465' => 'subscriptions',
            '750342442' => 'coupon_code',
            '844539743' => 'GHL: Migrate GHL', // Nuevo campo para marcar migración
        ];

        $payloadAttributes = [];
        $includedFields = [];
        $skippedFields = [];

        foreach ($mapping as $fieldId => $key) {
            // Check for possible key variations (with or without 'GHL:' prefix)
            $rawKey = $key;
            $prefixedKey = 'GHL: ' . ucfirst($key);
            $prefixedKeyNoSpace = 'GHL:' . ucfirst($key);
            $prefixedKeySubscriptions = 'GHL: Subscriptions';
            $prefixedKeySubscriptionsNew = 'GHL: Subscriptions New'; // Específico para este campo
            
            // Additional keys for "Subscriptions"
            $possibleKeys = [$rawKey, $prefixedKey, $prefixedKeyNoSpace];
            
            // Add special handling for subscription field
            if ($key === 'subscriptions') {
                $possibleKeys = array_merge($possibleKeys, [
                    $prefixedKeySubscriptions, 
                    $prefixedKeySubscriptionsNew, 
                    'GHL:Subscriptions', 
                    'subscription', 
                    'GHL: Subscription',
                    'GHL:Subscription',
                    'subscription_new',
                    'subscriptions_new',
                ]);
            }
            
            // Try to get the value using different possible key formats
            $value = null;
            foreach ($possibleKeys as $possibleKey) {
                if (isset($ghlData[$possibleKey])) {
                    $value = $ghlData[$possibleKey];
                    break;
                } elseif (isset($ghlData[strtolower($possibleKey)])) {
                    $value = $ghlData[strtolower($possibleKey)];
                    break;
                }
            }
            
            // Log the key being processed for debugging
            Log::debug("Processing field: {$key}", [
                'field_id' => $fieldId,
                'possible_keys' => $possibleKeys,
                'value_found' => $value !== null ? 'yes' : 'no',
                'value' => $value,
            ]);

            // Only include attributes with valid values (not null, not empty string)
            if ($value !== null && $value !== '') {
                // Convert boolean values to string format for Baremetrics API
                $formattedValue = $value;
                if (is_bool($value)) {
                    $formattedValue = $value ? 'true' : 'false';
                }
                
                $payloadAttributes[] = [
                    'customer_oid' => (string) $customerId,
                    'field_id' => (string) $fieldId,
                    'value' => $formattedValue,
                ];
                $includedFields[$key] = $formattedValue;
            } else {
                $skippedFields[$key] = $value;
            }
        }

        // Log which fields were included and which were skipped
        Log::debug('Fields processing results', [
            'included_fields' => $includedFields,
            'skipped_fields' => $skippedFields,
        ]);

        // If there's nothing to send, return early
        if (empty($payloadAttributes)) {
            Log::info('No valid attributes to update for customer in Baremetrics', [
                'customer_id' => $customerId,
            ]);
            return null;
        }

        $body = [
            'attributes' => $payloadAttributes,
        ];

        // Log the payload we're about to send
        Log::debug('Sending attributes payload to Baremetrics', [
            'payload' => $body,
            'url' => $url,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($url, $body);

        if ($response->successful()) {
            $responseData = $response->json();
            
            Log::info('Customer attributes updated successfully in Baremetrics', [
                'customer_id' => $customerId,
                'updated_fields' => array_keys($includedFields),
                'response' => $responseData,
            ]);
            
            return $responseData;
        }

        Log::error('Failed to update customer attributes in Baremetrics', [
            'customer_id' => $customerId,
            'status' => $response->status(),
            'response' => $response->body(),
            'request_payload' => $body,
        ]);
        
        return null;
    }

    /**
     * Get the API key for debugging purposes
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get the base URL for debugging purposes
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the current environment (sandbox or production)
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return config('services.baremetrics.environment', 'sandbox');
    }

    /**
     * Check if currently using sandbox environment
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->getEnvironment() === 'sandbox';
    }

    /**
     * Check if currently using production environment
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'production';
    }

    /**
     * Get the Barecancel JavaScript URL
     *
     * @return string
     */
    public function getBarecancelJsUrl(): string
    {
        return config('services.baremetrics.barecancel_js_url');
    }
    
    /**
     * Get all available custom fields from Baremetrics
     * This is useful for discovering field IDs
     *
     * @return array|null
     */
    public function getCustomFields(): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/attributes/fields');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Baremetrics API Error - Custom Fields', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Custom Fields', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }


    /**
     * Create a plan in Baremetrics
     * 
     * @param string $sourceId The source ID for the plan
     * @param array $planData Plan data to create
     * @return array|null
     */
    public function createPlan(array $planData, string $sourceId): ?array
    {
        try {
            // Ensure plan has an OID
            if (!isset($planData['oid'])) {
                $planData['oid'] = 'plan_' . uniqid();
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . "/{$sourceId}/plans", $planData);

            if ($response->successful()) {
                Log::info('Baremetrics Plan Created Successfully', [
                    'source_id' => $sourceId,
                    'plan_data' => $planData,
                    'response' => $response->json()
                ]);
                return $response->json();
            }

            Log::error('Baremetrics API Error - Create Plan', [
                'source_id' => $sourceId,
                'plan_data' => $planData,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Create Plan', [
                'source_id' => $sourceId,
                'plan_data' => $planData,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Find or create a plan in Baremetrics
     * First checks cache, then API, then creates a new one if needed
     * 
     * @param string $sourceId The source ID for the plan
     * @param array $planData Plan data to create
     * @return array|null
     */
    public function findOrCreatePlan(array $planData, string $sourceId): ?array
    {
        try {
            // Check cache first
            $cacheKey = "baremetrics_plan_{$sourceId}_{$planData['name']}";
            $cachedPlan = \Illuminate\Support\Facades\Cache::get($cacheKey);
            
            if ($cachedPlan) {
                Log::info('Baremetrics Plan Found In Cache (Reusing)', [
                    'source_id' => $sourceId,
                    'plan_name' => $planData['name'],
                    'cached_plan_oid' => $cachedPlan['oid'],
                    'plan_data' => $planData
                ]);
                return $cachedPlan;
            }

            // Try to find existing plan via API
            $existingPlan = $this->findPlanByName($planData['name'], $sourceId);
            
            if ($existingPlan) {
                // Cache the found plan
                \Illuminate\Support\Facades\Cache::put($cacheKey, $existingPlan, 86400); // 24 hours
                
                Log::info('Baremetrics Plan Found Via API (Reusing)', [
                    'source_id' => $sourceId,
                    'plan_name' => $planData['name'],
                    'existing_plan_oid' => $existingPlan['oid'],
                    'plan_data' => $planData
                ]);
                return $existingPlan;
            }

            // If no existing plan found, create a new one
            Log::info('Baremetrics Plan Not Found, Creating New', [
                'source_id' => $sourceId,
                'plan_name' => $planData['name'],
                'plan_data' => $planData
            ]);

            $newPlan = $this->createPlan($planData, $sourceId);
            
            if ($newPlan && isset($newPlan['plan']['oid'])) {
                // Cache the new plan
                \Illuminate\Support\Facades\Cache::put($cacheKey, $newPlan['plan'], 86400); // 24 hours
                
                Log::info('Baremetrics Plan Created And Cached', [
                    'source_id' => $sourceId,
                    'plan_name' => $planData['name'],
                    'new_plan_oid' => $newPlan['plan']['oid'],
                    'plan_data' => $planData
                ]);
                
                return $newPlan['plan'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Find Or Create Plan', [
                'source_id' => $sourceId,
                'plan_data' => $planData,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Find a plan by name in Baremetrics
     * 
     * @param string $planName The name of the plan to find
     * @param string $sourceId The source ID to search in
     * @return array|null
     */
    public function findPlanByName(string $planName, string $sourceId): ?array
    {
        try {
            $page = 1;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->getPlans($sourceId, $page, 100);
                
                if (!$response) {
                    Log::info('Baremetrics - No response from getPlans', [
                        'source_id' => $sourceId,
                        'page' => $page
                    ]);
                    break;
                }

                // Log the response structure for debugging
                Log::debug('Baremetrics - Plans API Response', [
                    'source_id' => $sourceId,
                    'page' => $page,
                    'response_structure' => array_keys($response),
                    'response' => $response
                ]);

                $plans = [];
                if (is_array($response) && isset($response['plans']) && is_array($response['plans'])) {
                    $plans = $response['plans'];
                } elseif (is_array($response)) {
                    $plans = $response;
                }

                Log::debug('Baremetrics - Extracted Plans', [
                    'source_id' => $sourceId,
                    'page' => $page,
                    'plans_count' => count($plans),
                    'plans' => $plans
                ]);

                // Search for plan with matching name
                foreach ($plans as $plan) {
                    if (isset($plan['name']) && $plan['name'] === $planName) {
                        Log::info('Baremetrics Plan Found By Name', [
                            'source_id' => $sourceId,
                            'plan_name' => $planName,
                            'plan_oid' => $plan['oid'] ?? 'N/A',
                            'plan' => $plan
                        ]);
                        return $plan;
                    }
                }

                // Check if there are more pages
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;
                usleep(100000); // Small pause between requests
            }
            
            Log::info('Baremetrics Plan Not Found By Name', [
                'source_id' => $sourceId,
                'plan_name' => $planName
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Find Plan By Name', [
                'source_id' => $sourceId,
                'plan_name' => $planName,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Create a subscription in Baremetrics
     * 
     * @param string $sourceId The source ID for the subscription
     * @param array $subscriptionData Subscription data to create
     * @return array|null
     */
    public function createSubscription(array $subscription, string $sourceId): ?array
    {
        try {

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . "/{$sourceId}/subscriptions", $subscription);

            if ($response->successful()) {
                Log::info('Baremetrics Subscription Created Successfully', [
                    'source_id' => $sourceId,
                    'subscription_data' => $subscription,
                    'response' => $response->json()
                ]);
                return $response->json();
            }

            Log::error('Baremetrics API Error - Create Subscription', [
                'source_id' => $sourceId,
                'subscription_data' => $subscription,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Create Subscription', [
                'source_id' => $sourceId,
                'subscription_data' => $subscription,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get source ID from Baremetrics
     * This is required for creating customers, plans, and subscriptions
     * 
     * @return string|null
     */
    public function getSourceId(): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/sources');

            if ($response->successful()) {
                $sources = $response->json();
                
                // Return the first source ID if available
                if (!empty($sources['sources']) && is_array($sources['sources'])) {
                    return $sources['sources'][0]['id'] ?? null;
                }
            }

            Log::error('Baremetrics API Error - Get Sources', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Get Sources', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Delete a customer from Baremetrics
     * Note: All subscriptions for this customer must be deleted first
     * 
     * @param string $sourceId The source ID
     * @param string $customerOid The customer OID to delete
     * @return bool
     */
    public function deleteCustomer(string $sourceId, string $customerOid): bool
    {
        try {
            $url = $this->baseUrl . '/' . $sourceId . '/customers/' . $customerOid;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->delete($url);

            if ($response->successful()) {
                Log::info('Baremetrics Customer Deleted Successfully', [
                    'source_id' => $sourceId,
                    'customer_oid' => $customerOid,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('Baremetrics API Error - Delete Customer', [
                'source_id' => $sourceId,
                'customer_oid' => $customerOid,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Delete Customer', [
                'source_id' => $sourceId,
                'customer_oid' => $customerOid,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Delete a subscription from Baremetrics
     * 
     * @param string $sourceId The source ID
     * @param string $subscriptionOid The subscription OID to delete
     * @return bool
     */
    public function deleteSubscription(string $sourceId, string $subscriptionOid): bool
    {
        try {
            $url = $this->baseUrl . '/' . $sourceId . '/subscriptions/' . $subscriptionOid;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->delete($url);

            if ($response->successful()) {
                Log::info('Baremetrics Subscription Deleted Successfully', [
                    'source_id' => $sourceId,
                    'subscription_oid' => $subscriptionOid,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('Baremetrics API Error - Delete Subscription', [
                'source_id' => $sourceId,
                'subscription_oid' => $subscriptionOid,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Delete Subscription', [
                'source_id' => $sourceId,
                'subscription_oid' => $subscriptionOid,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Create a complete customer setup (customer + plan + subscription) in Baremetrics
     * 
     * @param array $customerData Customer data
     * @param array $planData Plan data
     * @param array $subscriptionData Subscription data
     * @return array|null
     */
    public function createCompleteCustomerSetup(array $customerData, array $planData, array $subscriptionData): ?array
    {
        try {
            // Get source ID
            $sourceId = $this->getSourceId();
            if (!$sourceId) {
                Log::error('Baremetrics - No source ID available');
                return null;
            }

            // Create customer
            $customer = $this->createCustomer($customerData, $sourceId);
            if (!$customer || !isset($customer['customer']['oid'])) {
                Log::error('Baremetrics - Failed to create customer');
                return null;
            }

            // Find or create plan
            $plan = $this->findOrCreatePlan($planData, $sourceId);
            if (!$plan || !isset($plan['oid'])) {
                Log::error('Baremetrics - Failed to find or create plan');
                return null;
            }

            // Add customer and plan OIDs to subscription data
            $subscriptionData['customer_oid'] = $customer['customer']['oid'];
            $subscriptionData['plan_oid'] = $plan['oid'];

            // Create subscription
            $subscription = $this->createSubscription($subscriptionData, $sourceId);
            if (!$subscription) {
                Log::error('Baremetrics - Failed to create subscription');
                return null;
            }

            Log::info('Baremetrics Complete Customer Setup Created Successfully', [
                'source_id' => $sourceId,
                'customer_oid' => $customer['customer']['oid'],
                'plan_oid' => $plan['oid'],
                'subscription' => $subscription
            ]);

            return [
                'customer' => $customer,
                'plan' => $plan,
                'subscription' => $subscription,
                'source_id' => $sourceId
            ];

        } catch (\Exception $e) {
            Log::error('Baremetrics Service Exception - Complete Customer Setup', [
                'customer_data' => $customerData,
                'plan_data' => $planData,
                'subscription_data' => $subscriptionData,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Get GHL source ID from Baremetrics
     * 
     * @return string|null
     */
    public function getGHLSourceId(): ?string
    {
        try {
            $sources = $this->getSources();
            
            if (!$sources) {
                Log::error('No se pudieron obtener sources de Baremetrics');
                return null;
            }

            $sourcesList = [];
            if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
                $sourcesList = $sources['sources'];
            } elseif (is_array($sources)) {
                $sourcesList = $sources;
            }

            // Buscar source que sea de Baremetrics (no Stripe) para GHL
            foreach ($sourcesList as $source) {
                $provider = $source['provider'] ?? '';
                $sourceId = $source['id'] ?? '';
                
                // El source de GHL es el que tiene provider "baremetrics"
                if ($provider === 'baremetrics') {
                    Log::info('Source ID de GHL encontrado', [
                        'source_id' => $sourceId,
                        'provider' => $provider
                    ]);
                    
                    return $sourceId;
                }
            }

            // Si no encuentra uno específico de GHL, usar el por defecto
            Log::warning('No se encontró source específico de GHL, usando source por defecto');
            return 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        } catch (\Exception $e) {
            Log::error('Error obteniendo source ID de GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback al source por defecto
            return 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';
        }
    }
}
