<?php

namespace App\Services;
use App\Models\Configuration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

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
        $this->apiKey = $this->config->ghl_client_id ?: config('services.gohighlevel.client_id');
        $this->base_url = 'https://services.leadconnectorhq.com';
        $this->location = config('services.gohighlevel.location');
        $this->client = new Client();
    }

    public function getToken($code)
    {
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
        Log::info('GoHighLevel access token', ['data' => $data ?? null]);
        return $data['access_token'] ?? null;
    }

    public function getLocation()
    {
        $response = $this->client->request('GET', "{$this->base_url}/locations", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->ghl_token}",
                'Version' => '2021-07-28',
            ],
        ]);

        return $response->getBody();
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
            $response = $this->client->request('POST', "{$this->base_url}/contacts/search", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->config->ghl_token}",
                    'Version' => '2021-07-28',
                ],
                'json' => $body,
            ]);

            // Verificar que la respuesta sea exitosa
            if ($response->getStatusCode() !== 200) {
                throw new Exception("Error en la API: " . $response->getStatusCode());
            }

            // Devolver el contenido decodificado
            return json_decode($response->getBody()->getContents(), true);

        } catch (Exception $e) {
            // Manejar el error apropiadamente
            throw new Exception("Error al obtener contactos: " . $e->getMessage());
        }
    }

    public function getCustomFields()
    {
        $response = $this->client->request('GET', "{$this->base_url}/locations/{$this->location}/customFields", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$this->config->ghl_token}",
                'Version' => '2021-07-28',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}