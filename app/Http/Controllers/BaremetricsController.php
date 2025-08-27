<?php

namespace App\Http\Controllers;

use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

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
        // Dispatch the job to perform the update in background (or sync depending on queue driver)
        $cacheKey = 'baremetrics:update_fields:progress';
        Cache::put($cacheKey, [
            'status' => 'queued',
            'updated' => 0,
            'total' => 0,
            'current_email' => null,
            'started_at' => now()->toDateTimeString(),
        ], 60 * 60);

        try {
            \App\Jobs\UpdateBaremetricsFromGHLJob::dispatch();
            return response()->json(['success' => true, 'message' => 'Proceso encolado']);
        } catch (\Exception $e) {
            Log::error('Error dispatching UpdateBaremetricsFromGHLJob: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'No se pudo iniciar el proceso'], 500);
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
     * Return view that allows admin to start the update and see progress
     */
    public function showUpdateFields()
    {
        return view('baremetrics.update_fields');
    }

    /**
     * Return current progress as JSON
     */
    public function getUpdateStatus(): JsonResponse
    {
        $cacheKey = 'baremetrics:update_fields:progress';
        $progress = Cache::get($cacheKey, [
            'status' => 'idle',
            'updated' => 0,
            'total' => 0,
            'current_email' => null,
        ]);

        return response()->json(['success' => true, 'data' => $progress]);
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
