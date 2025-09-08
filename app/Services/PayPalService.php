<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected $clientId;
    protected $secret;
    protected $baseUrl;
    protected $accessToken;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->secret = config('services.paypal.secret');
        $this->baseUrl = config('services.paypal.sandbox') 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com';
        $this->accessToken = $this->getAccessToken();
    }

    protected function getAccessToken()
    {
        $response = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post("$this->baseUrl/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('Failed to get PayPal access token', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
        
        return null;
    }

    /**
     * Get subscriptions from PayPal API with pagination support
     *
     * @param array $params Query parameters
     * @return array
     */
    public function getSubscriptions($params = [])
    {
        try {
            if (!$this->accessToken) {
                $this->accessToken = $this->getAccessToken();
                if (!$this->accessToken) {
                    return [
                        'success' => false,
                        'message' => 'Failed to authenticate with PayPal API'
                    ];
                }
            }

            // Set default parameters
            $defaultParams = [
                'status' => 'ACTIVE',
                'page' => 1,
                'page_size' => 10,
                'total_required' => 'true'
            ];

            // Merge with provided parameters
            $queryParams = array_merge($defaultParams, $params);

            // Map our parameter names to PayPal's expected parameter names
            $paypalParams = [
                'status' => $queryParams['status'],
                'page' => $queryParams['page'],
                'page_size' => $queryParams['page_size'],
                'total_required' => $queryParams['total_required']
            ];

            // Add email filter if provided
            if (isset($queryParams['email_address'])) {
                $paypalParams['subscriber_email'] = $queryParams['email_address'];
            }

            // Make the API request
            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Prefer' => 'return=representation',
                    'Content-Type' => 'application/json',
                ])
                ->get("$this->baseUrl/v1/billing/subscriptions", $paypalParams);

            if ($response->successful()) {
                $data = $response->json();
                
                // Format the response to be more consistent
                $result = [
                    'subscriptions' => $data['subscriptions'] ?? [],
                    'total_items' => $data['total_items'] ?? 0,
                    'total_pages' => $data['total_pages'] ?? 1,
                    'links' => $data['links'] ?? []
                ];

                return [
                    'success' => true,
                    'data' => $result
                ];
            }

            // Handle specific error cases
            if ($response->status() === 401) {
                // Token might be expired, try to refresh it once
                $this->accessToken = $this->getAccessToken();
                return $this->getSubscriptions($params);
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['message'] ?? 'Failed to fetch subscriptions';
            
            Log::error('PayPal API Error', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'details' => $errorResponse['details'] ?? null,
                'params' => $params
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'details' => $errorResponse,
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('PayPal Service Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Service unavailable. Please try again later.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get details of a specific subscription
     *
     * @param string $subscriptionId PayPal subscription ID
     * @return array
     */
    public function getSubscriptionDetails($subscriptionId)
    {
        try {
            if (!$this->accessToken) {
                $this->accessToken = $this->getAccessToken();
                if (!$this->accessToken) {
                    return [
                        'success' => false,
                        'message' => 'Failed to authenticate with PayPal API'
                    ];
                }
            }

            $response = Http::withToken($this->accessToken)
                ->withHeaders([
                    'Prefer' => 'return=representation',
                    'Content-Type' => 'application/json',
                ])
                ->get("$this->baseUrl/v1/billing/subscriptions/$subscriptionId");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            // Handle specific error cases
            if ($response->status() === 401) {
                // Token might be expired, try to refresh it once
                $this->accessToken = $this->getAccessToken();
                return $this->getSubscriptionDetails($subscriptionId);
            }

            $errorResponse = $response->json();
            $errorMessage = $errorResponse['message'] ?? 'Failed to fetch subscription details';
            
            Log::error('PayPal Subscription Details Error', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'error' => $errorMessage,
                'details' => $errorResponse['details'] ?? null
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'details' => $errorResponse,
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('PayPal Service Exception', [
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve subscription details. Please try again later.',
                'error' => $e->getMessage()
            ];
        }
    }
}
