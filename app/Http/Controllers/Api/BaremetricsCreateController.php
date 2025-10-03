<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BaremetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BaremetricsCreateController extends Controller
{
    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Create a customer in Baremetrics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCustomer(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'company' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'source_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sourceId = $request->input('source_id') ?: $this->baremetricsService->getSourceId();
            
            if (!$sourceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el Source ID de Baremetrics'
                ], 500);
            }

            $customerData = $request->only(['name', 'email', 'company', 'notes']);
            $result = $this->baremetricsService->createCustomer($customerData, $sourceId);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cliente creado exitosamente',
                    'data' => $result
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear el cliente en Baremetrics'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en createCustomer API', [
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
     * Create a plan in Baremetrics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPlan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'interval' => 'required|string|in:day,week,month,year',
                'interval_count' => 'required|integer|min:1',
                'amount' => 'required|integer|min:0',
                'currency' => 'required|string|size:3',
                'trial_days' => 'nullable|integer|min:0',
                'notes' => 'nullable|string|max:1000',
                'source_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sourceId = $request->input('source_id') ?: $this->baremetricsService->getSourceId();
            
            if (!$sourceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el Source ID de Baremetrics'
                ], 500);
            }

            $planData = $request->only([
                'name', 'interval', 'interval_count', 'amount', 
                'currency', 'trial_days', 'notes'
            ]);
            
            $result = $this->baremetricsService->createPlan($planData, $sourceId);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Plan creado exitosamente',
                    'data' => $result
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear el plan en Baremetrics'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en createPlan API', [
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
     * Create a subscription in Baremetrics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSubscription(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_oid' => 'required|string',
                'plan_oid' => 'required|string',
                'started_at' => 'required|integer',
                'status' => 'required|string|in:active,canceled,past_due,trialing',
                'notes' => 'nullable|string|max:1000',
                'source_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sourceId = $request->input('source_id') ?: $this->baremetricsService->getSourceId();
            
            if (!$sourceId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el Source ID de Baremetrics'
                ], 500);
            }

            $subscriptionData = $request->only([
                'customer_oid', 'plan_oid', 'started_at', 'status', 'notes'
            ]);
            
            $result = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Suscripción creada exitosamente',
                    'data' => $result
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la suscripción en Baremetrics'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en createSubscription API', [
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
     * Create a complete customer setup (customer + plan + subscription)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCompleteSetup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer' => 'required|array',
                'customer.name' => 'required|string|max:255',
                'customer.email' => 'required|email|max:255',
                'customer.company' => 'nullable|string|max:255',
                'customer.notes' => 'nullable|string|max:1000',
                
                'plan' => 'required|array',
                'plan.name' => 'required|string|max:255',
                'plan.interval' => 'required|string|in:day,week,month,year',
                'plan.interval_count' => 'required|integer|min:1',
                'plan.amount' => 'required|integer|min:0',
                'plan.currency' => 'required|string|size:3',
                'plan.trial_days' => 'nullable|integer|min:0',
                'plan.notes' => 'nullable|string|max:1000',
                
                'subscription' => 'required|array',
                'subscription.started_at' => 'required|integer',
                'subscription.status' => 'required|string|in:active,canceled,past_due,trialing',
                'subscription.notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $customerData = $request->input('customer');
            $planData = $request->input('plan');
            $subscriptionData = $request->input('subscription');
            
            $result = $this->baremetricsService->createCompleteCustomerSetup(
                $customerData, 
                $planData, 
                $subscriptionData
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Configuración completa de cliente creada exitosamente',
                    'data' => $result
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la configuración completa en Baremetrics'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en createCompleteSetup API', [
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
     * Get source ID from Baremetrics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSourceId()
    {
        try {
            $sourceId = $this->baremetricsService->getSourceId();

            if ($sourceId) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'source_id' => $sourceId
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el Source ID de Baremetrics'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error en getSourceId API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
