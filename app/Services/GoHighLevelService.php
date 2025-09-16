<?php

namespace App\Services;
use App\Models\Configuration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Exception;
use Carbon\Carbon;

class GoHighLevelService
{
    private $baseUrl;
    private $apiKey;
    private $client;
    private $base_url;
    private $token;
    private $location;
    private $config;

    public function __construct()
    {
        $this->config = Configuration::first();
        $this->apiKey = config('services.gohighlevel.client_id');
        $this->base_url = 'https://services.leadconnectorhq.com';
        $this->location = config('services.gohighlevel.location');
        $this->client = new Client();
    }

    public function getToken($code)
    {
        try {
            $response = $this->client->request('POST', "{$this->base_url}/oauth/token", [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('services.gohighlevel.client_id'),
                    'client_secret' => config('services.gohighlevel.client_secret'),
                    'code' => $code,
                    'user_type' => 'Location'
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['access_token'])) {
                Log::error('GoHighLevel authentication failed', ['response' => $data]);
                throw new \Exception('No se pudo obtener el token de acceso');
            }
            
            // Guardar los tokens y fecha de expiración en la configuración
            if ($this->config) {
                $this->config->ghl_token = $data['access_token'];
                $this->config->ghl_refresh_token = $data['refresh_token'] ?? null;
                
                // Calcular y guardar la fecha de expiración (normalmente los tokens duran 24 horas)
                $expiresIn = $data['expires_in'] ?? 86400; // 24 horas por defecto
                $this->config->ghl_token_expires_at = now()->addSeconds($expiresIn);
                
                $this->config->save();
                
                Log::info('GoHighLevel authentication successful', [
                    'expires_at' => $this->config->ghl_token_expires_at
                ]);
            } else {
                Log::warning('GoHighLevel token obtained but no configuration model available to save it');
            }
            
            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error('Error obtaining GoHighLevel token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getLocation()
    {
        try {
            // Asegurarnos de tener un token válido
            $token = $this->ensureValidToken();
            
            $response = $this->client->request('GET', "{$this->base_url}/locations", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                    'Version' => '2021-07-28',
                ],
            ]);

            return $response->getBody();
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Si obtenemos un 401 Unauthorized, intentar refrescar el token y reintentar
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $token = $this->refreshToken();
                
                $response = $this->client->request('GET', "{$this->base_url}/locations", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$token}",
                        'Version' => '2021-07-28',
                    ],
                ]);
                
                return $response->getBody();
            }
            
            throw $e;
        }
    }

    public function refreshToken()
    {
        try {
            Log::debug("Intentando refrescar el token de GoHighLevel");
            
            $response = $this->client->request('POST', "{$this->base_url}/oauth/token", [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config->ghl_client_id ?: config('services.gohighlevel.client_id'),
                    'client_secret' => $this->config->ghl_client_secret ?: config('services.gohighlevel.client_secret'),
                    'refresh_token' => $this->config->ghl_refresh_token,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!isset($data['access_token'])) {
                Log::error('GoHighLevel refresh token falló', ['response' => $data]);
                throw new \Exception('No se pudo obtener un nuevo access token');
            }
            
            // Guardar el nuevo token y refresh token en la configuración
            $this->config->ghl_token = $data['access_token'];
            if (isset($data['refresh_token'])) {
                $this->config->ghl_refresh_token = $data['refresh_token'];
            }
            
            // Calcular y guardar la fecha de expiración (normalmente los tokens duran 24 horas)
            $expiresIn = $data['expires_in'] ?? 86400; // 24 horas por defecto
            $this->config->ghl_token_expires_at = now()->addSeconds($expiresIn);
            
            $this->config->save();
            
            Log::info('GoHighLevel token actualizado correctamente', [
                'expires_at' => $this->config->ghl_token_expires_at
            ]);
            
            return $data['access_token'];
        } catch (\Exception $e) {
            Log::error('Error al refrescar el token de GoHighLevel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Verifica si el token actual ha expirado y lo renueva si es necesario
     */
    private function ensureValidToken()
    {
        // Si no hay fecha de expiración o el token expira en menos de 5 minutos, refrescarlo
        if (!$this->config->ghl_token_expires_at || 
            now()->addMinutes(5)->gte($this->config->ghl_token_expires_at)) {
            try {
                $this->refreshToken();
            } catch (\Exception $e) {
                Log::error('No se pudo refrescar el token automáticamente', [
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('El token de GoHighLevel ha expirado y no se pudo refrescar automáticamente');
            }
        }
        
        return $this->config->ghl_token;
    }

    public function getContacts($email = null)
    {
        $body = [
            'pageLimit' => 20,
            'locationId' => $this->location,
        ];

        if ($email) {
            $body['filters'] = [
                [
                    'field' => 'email',
                    'operator' => 'contains',
                    'value' => $email
                ]
            ];
        }

        try {
            // Asegurarnos de tener un token válido
            $token = $this->ensureValidToken();
            
            $response = $this->client->request('POST', "{$this->base_url}/contacts/search", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                    'Version' => '2021-07-28',
                ],
                'json' => $body,
            ]);

            // Verificar que la respuesta sea exitosa
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Error en la API: " . $response->getStatusCode());
            }

            // Devolver el contenido decodificado
            return json_decode($response->getBody()->getContents(), true);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Si obtenemos un 401 Unauthorized, intentar refrescar el token y reintentar
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                Log::warning('Token de GoHighLevel inválido, intentando renovar', [
                    'error' => $e->getMessage()
                ]);
                
                try {
                    $token = $this->refreshToken();
                    
                    // Reintentar la solicitud con el nuevo token
                    $response = $this->client->request('POST', "{$this->base_url}/contacts/search", [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                            'Authorization' => "Bearer {$token}",
                            'Version' => '2021-07-28',
                        ],
                        'json' => $body,
                    ]);
                    
                    return json_decode($response->getBody()->getContents(), true);
                } catch (\Exception $refreshError) {
                    Log::error('Error al refrescar token y reintentar solicitud', [
                        'error' => $refreshError->getMessage()
                    ]);
                    throw new \Exception("Error al refrescar el token de GoHighLevel: " . $refreshError->getMessage());
                }
            }
            
            // Para otros errores, relanzar la excepción
            Log::error('Error al obtener contactos de GoHighLevel', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw new \Exception("Error al obtener contactos: " . $e->getMessage());
        } catch (\Exception $e) {
            // Manejar otros tipos de errores
            Log::error('Error al obtener contactos de GoHighLevel', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error al obtener contactos: " . $e->getMessage());
        }
    }

    public function getCustomFields()
    {
        try {
            // Asegurarnos de tener un token válido
            $token = $this->ensureValidToken();
            
            $response = $this->client->request('GET', "{$this->base_url}/locations/{$this->location}/customFields", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                    'Version' => '2021-07-28',
                ],
            ]);

            return json_decode($response->getBody(), true);
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Si obtenemos un 401 Unauthorized, intentar refrescar el token y reintentar
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
                $token = $this->refreshToken();
                
                $response = $this->client->request('GET', "{$this->base_url}/locations/{$this->location}/customFields", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$token}",
                        'Version' => '2021-07-28',
                    ],
                ]);
                
                return json_decode($response->getBody(), true);
            }
            
            Log::error('Error al obtener custom fields de GoHighLevel', [
                'error' => $e->getMessage(),
                'response' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw new \Exception("Error al obtener custom fields: " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error al obtener custom fields de GoHighLevel', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error al obtener custom fields: " . $e->getMessage());
        }
    }

    public function getSubscriptionStatusByContact(string $contactId)
    {
        // Asegurarnos de tener un token válido
        $token = $this->ensureValidToken();

        try {
            $headers = [
                'Accept' => 'application/json',
                'Version' => '2021-07-28',
                'Authorization' => "Bearer {$token}"
            ];
            
            $request = new \GuzzleHttp\Psr7\Request(
                'GET', 
                "{$this->base_url}/payments/subscriptions?altId={$this->location}&altType=location&contactId={$contactId}", 
                $headers
            );
            
            $res = $this->client->sendAsync($request)->wait();
            $data = json_decode($res->getBody(), true);

            return $data['data'][0]['status'];

        } catch (\Exception $e) {
            Log::error('Error al obtener datos de suscripción por ID de contacto', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error al obtener datos de suscripción por ID de contacto: " . $e->getMessage());
        }
    }

    /**
     * Get subscription data for a contact by ID
     *
     * @param string $contactId The GHL contact ID
     * @return array|null The subscription information including status and details
     */
    /**
     * Get subscription data for a contact by email
     *
     * @param string $email The contact's email address
     * @return array|null The subscription information including status and details
     */
    public function getContactMembershipByEmail(string $email)
    {
        try {
            // First, find the contact by email
            $contacts = $this->getContacts($email);
            
            if (empty($contacts) || !isset($contacts['contacts']) || empty($contacts['contacts'])) {
                Log::warning('No contact found with the provided email', ['email' => $email]);
                return null;
            }
            
            // Use the first contact if multiple are found
            $contact = $contacts['contacts'][0];
            $contactId = $contact['id'];
            
            Log::info('Found contact by email', [
                'email' => $email,
                'contact_id' => $contactId,
                'name' => $contact['name'] ?? 'No name'
            ]);

            return response()->json($contact);
            
            // Now get the membership using the contact ID
            return $this->getContactMembership($contactId);
            
            
        } catch (\Exception $e) {
            Log::error('Error al obtener datos de suscripción por email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Error al obtener datos de suscripción por email: " . $e->getMessage());
        }
    }

    public function getContactMembership(string $contactId)
    {
        $token = $this->ensureValidToken();
        // Obtenemos los datos de suscripción del contacto
            // Primero intentamos obtener las suscripciones directamente
            try {
                $response = $this->client->request('GET', "{$this->base_url}/contacts/{$contactId}/subscriptions", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$token}",
                        'Version' => '2021-07-28',
                    ],
                    'query' => [
                        'locationId' => $this->location
                    ]
                ]);

                $subscriptions = json_decode($response->getBody()->getContents(), true);
                
                // Registro para debug
                Log::info('Suscripciones obtenidas de GoHighLevel', [
                    'contact_id' => $contactId,
                    'subscriptions' => $subscriptions
                ]);
                
                return [
                    'type' => 'subscriptions',
                    'data' => $subscriptions
                ];
            } catch (\Exception $e) {
                // Si no podemos obtener las suscripciones directamente, intentamos con el campo personalizado
                Log::warning('No se pudieron obtener las suscripciones directamente, intentando obtener campos personalizados', [
                    'error' => $e->getMessage()
                ]);
                
                // Obtenemos los campos personalizados del contacto
                $response = $this->client->request('GET', "{$this->base_url}/contacts/{$contactId}/customFields", [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Authorization' => "Bearer {$token}",
                        'Version' => '2021-07-28',
                    ],
                    'query' => [
                        'locationId' => $this->location
                    ]
                ]);
                
                $customFields = json_decode($response->getBody()->getContents(), true);
                
                // Buscamos campos relacionados con suscripciones
                $subscriptionData = [];
                if (isset($customFields['customFields']) && is_array($customFields['customFields'])) {
                    foreach ($customFields['customFields'] as $field) {
                        // Buscar campos que contengan 'subscription' o 'suscripción' en el nombre
                        if (stripos($field['name'], 'subscription') !== false || 
                            stripos($field['name'], 'suscripción') !== false) {
                            $subscriptionData[$field['name']] = $field['value'] ?? null;
                        }
                    }
                }
                
                // Registro para debug
                Log::info('Datos de suscripción obtenidos de campos personalizados', [
                    'contact_id' => $contactId,
                    'subscription_data' => $subscriptionData
                ]);
                
                return [
                    'type' => 'custom_fields',
                    'data' => $subscriptionData
                ];
            }
    }
}