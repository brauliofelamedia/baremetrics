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
                $url = $this->baseUrl . '/' . $sourceId . '/customers?search=' . $search . '&sort=created&page=' . $page . '&order=asc&per_page=100';

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

    public function updateCustomerAttributes($customerId, $ghlData)
    {
        $url = $this->baseUrl . '/attributes';

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
        ];

        $payloadAttributes = [];

        foreach ($mapping as $fieldId => $key) {
            $value = $ghlData[$key] ?? null;

            // Only include attributes with non-null values
            if ($value !== null) {
                $payloadAttributes[] = [
                    'customer_oid' => (string) $customerId,
                    'field_id' => (string) $fieldId,
                    'value' => $value,
                ];
            }
        }

        // If there's nothing to send, return early
        if (empty($payloadAttributes)) {
            return null;
        }

        $body = [
            'attributes' => $payloadAttributes,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($url, $body);

        if ($response->successful()) {
            return $response->json();

            Log::info('Customer attributes updated successfully in Baremetrics', [
                'customer_id' => $customerId,
            ]);
        }

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
}
