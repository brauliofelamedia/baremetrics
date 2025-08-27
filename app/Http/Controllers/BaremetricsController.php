<?php

namespace App\Http\Controllers;

use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use Log;

class BaremetricsController extends Controller
{
    private BaremetricsService $baremetricsService;
    private GoHighLevelService $ghlService;

    public function __construct(BaremetricsService $baremetricsService,GoHighLevelService $goHighLevelService)
    {
        $this->baremetricsService = $baremetricsService;
        $this->ghlService = $goHighLevelService;
    }

    /**
     * Get account information from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccount()
    {
        $account = $this->baremetricsService->getAccount();

        if ($account) {
            return response()->json([
                'success' => true,
                'data' => $account
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener la información de la cuenta'
        ], 500);
    }

    /**
     * Get sources from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSources()
    {
        $sources = $this->baremetricsService->getSources();

        if ($sources) {
            return response()->json([
                'success' => true,
                'data' => $sources
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener las fuentes de datos'
        ], 500);
    }

    /**
     * Get users from Baremetrics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers()
    {
        $users = $this->baremetricsService->getUsers();

        if ($users) {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener la información de usuarios'
        ], 500);
    }

    /**
     * Get plans from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPlans(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $plans = $this->baremetricsService->getPlans($sourceId, $page, $perPage);

        if ($plans) {
            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener los planes para la fuente especificada'
        ], 500);
    }

    /**
     * Get customers from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomers(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $customers = $this->baremetricsService->getCustomers($sourceId, $page, $perPage);

        if ($customers) {
            return response()->json([
                'success' => true,
                'data' => $customers
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener los clientes para la fuente especificada'
        ], 500);
    }

    /**
     * Get subscriptions from Baremetrics for a specific source
     *
     * @param \Illuminate\Http\Request $request
     * @param string $sourceId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubscriptions(Request $request, string $sourceId)
    {
        $page = $request->query('page');
        $perPage = $request->query('per_page');

        $subscriptions = $this->baremetricsService->getSubscriptions($sourceId, $page, $perPage);

        if ($subscriptions) {
            return response()->json([
                'success' => true,
                'data' => $subscriptions
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se pudo obtener las suscripciones para la fuente especificada'
        ], 500);
    }

    public function updateCustomerFieldsFromGHL()
    {
        $sources = $this->baremetricsService->getSources();

        // Normalizar la estructura
        $sourcesNew = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesNew = $sources['sources'];
        } elseif (is_array($sources)) {
            // Si getCustomers ya devolvió directamente un array de sources
            $sourcesNew = $sources;
        }

        // Filtrar sólo providers 'stripe'
        $stripeSources = array_values(array_filter($sourcesNew, function ($source) {
            return isset($source['provider']) && $source['provider'] === 'stripe';
        }));

        // Extraer solo los IDs (filtrando vacíos)
        $sourceIds = array_values(array_filter(array_column($stripeSources, 'id'), function ($id) {
            return !empty($id);
        }));

        // Iteramos la lista de sources para obtener los clientes con paginación
        $customersExtract = [];
        foreach ($sourceIds as $sourceId) {
            $page = 0;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomersAll($sourceId,$page);

                if (!$response) {
                    $hasMore = false;
                    continue;
                }

                // Extraer los clientes y la información de paginación
                $customers = [];
                $pagination = [];
                
                if (isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    // Si la respuesta es directamente un array de clientes
                    $customers = $response;
                }
                
                // Obtener información de paginación
                if (isset($response['meta']['pagination'])) {
                $pagination = $response['meta']['pagination'];
                $hasMore = $pagination['has_more'] ?? false;

                } else {
                    // Si no hay información de paginación, asumimos que no hay más páginas
                    $hasMore = false;
                }
                
                // Agregar clientes al array
                if (!empty($customers)) {
                    $customersExtract = array_merge($customersExtract, $customers);
                }
                
                // Incrementar página para la siguiente iteración
                $page++;
                
                // Pequeña pausa para evitar sobrecargar la API
                usleep(100000); // 100ms
            }
        }

        foreach($customersExtract as $customer)
        {
            //Obtener la data de GHL
            $ghl_customer = $this->ghlService->getContacts($customer['email']);

            if(!empty($ghl_customer['contacts'])){

                $ghl_customer_data = $ghl_customer['contacts'][0];
    
                Log::info('Clientes que tienen cuenta en GHL', [
                    'id' => $ghl_customer_data['id'] ?? '-',
                    'email' => $customer['email']
                ]);

                //Obtenemos la info de GHL que se va a pasar
                //j175N7HO84AnJycpUb9D - Field score
                //JuiCbkHWsSc3iKfmOBpo - Field que signo eres
                //xy0zfzMRFpOdXYJkHS2c - Field tienes hijos
                //1fFJJsONHbRMQJCstvg1 - Field estas casada
                //q3BHfdxzT2uKfNO3icXG - Field donde naciste y creciste

                //Buscar dentro del array de custom fields
                $customFields = collect($ghl_customer['contacts'][0]['customFields']);

                //Signo zodiaco
                $country = $ghl_customer['contacts'][0]['country'] ?? '-';
                $city = $ghl_customer['contacts'][0]['city'] ?? '-';
                $score = $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D');
                $birthplace = $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG');
                $sign = $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo');
                $hasKids = $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c');
                $isMarried = $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1');

                $ghlData = [
                    'relationship_status' => $isMarried['value'] ?? '-',
                    'community_location' => $birthplace['value'] ?? '-',
                    'country' => $country ?? '-',
                    'engagement_score' => $score['value'] ?? '-',
                    'has_kids' => $hasKids['value'] ?? '-',
                    'state' => $ghl_customer['contacts'][0]['state'] ?? '-',
                    'location' => $city,
                    'zodiac_sign' => $sign['value'] ?? '-',
                ];

                $this->updateAttributes($customer['oid'], $ghlData);
            }
        }
    }

    public function updateAttributes($customerId,$ghlData)
    {
        try {
            $result = $this->baremetricsService->updateCustomerAttributes($customerId, $ghlData);

        } catch (\Exception $e) {
            Log::error("Error al actualizar campos GHL: " . $e->getMessage());
        }
    }

    /**
     * Get API configuration and environment information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig()
    {
        try {
            $config = [
                'environment' => $this->baremetricsService->getEnvironment(),
                'base_url' => $this->baremetricsService->getBaseUrl(),
                'is_sandbox' => $this->baremetricsService->isSandbox(),
                'is_production' => $this->baremetricsService->isProduction(),
                // Note: No incluimos la API key por seguridad
            ];

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la configuración'
            ], 500);
        }
    }

    /**
     * Show Baremetrics dashboard
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $account = $this->baremetricsService->getAccount();
        return view('baremetrics.dashboard', compact('account'));
    }
}
