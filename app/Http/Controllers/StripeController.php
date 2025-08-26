<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Log;

class StripeController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    public function getCustomers(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 100);
        $startingAfter = $request->get('starting_after');

        $result = $this->stripeService->getCustomerIds($limit, $startingAfter);

        return response()->json($result);
    }

    public function getAllCustomers(): JsonResponse
    {
        $result = $this->stripeService->getAllCustomerIds();

        return response()->json($result);
    }

    public function getCustomer($customerId): JsonResponse
    {
        $result = $this->stripeService->getCustomer($customerId);

        return response()->json($result);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $result = $this->stripeService->searchCustomersByEmail($request->email);

        return response()->json($result);
    }

    public function getPublishableKey(): JsonResponse
    {
        return response()->json([
            'publishable_key' => $this->stripeService->getPublishableKey()
        ]);
    }

    public function index()
    {
        try {
            // Primero verificar la conectividad
            $testConnection = $this->stripeService->testConnection();
            if (!$testConnection['success']) {
                return view('stripe.customers', [
                    'customers' => ['success' => false, 'data' => [], 'error' => $testConnection['error']],
                    'pagination' => ['has_more' => false, 'starting_after' => null]
                ]);
            }

            // Obtener solo los primeros customers con paginación (50 por página)
            $customers = $this->stripeService->getCustomerIds(50);
            
            if (!$customers['success']) {
                return view('stripe.customers', [
                    'customers' => ['success' => false, 'data' => [], 'error' => $customers['error']],
                    'pagination' => ['has_more' => false, 'starting_after' => null]
                ]);
            }

            // Preparar información de paginación
            $pagination = [
                'has_more' => $customers['has_more'] ?? false,
                'starting_after' => !empty($customers['data']) ? end($customers['data'])['id'] : null,
                'current_count' => count($customers['data'])
            ];

            return view('stripe.customers', compact('customers', 'pagination'));

        } catch (\Exception $e) {
            \Log::error('Error al cargar la vista de customers de Stripe: ' . $e->getMessage());
            
            return view('stripe.customers', [
                'customers' => [
                    'success' => false, 
                    'data' => [], 
                    'error' => 'Error inesperado al cargar los clientes'
                ],
                'pagination' => ['has_more' => false, 'starting_after' => null]
            ]);
        }
    }

    public function cancelSubscription(Request $request)
    {
        $customer_id = $request->input('customer_id');
        $priceId = $request->input('subscription_id');

        Log::info('Cancelando suscripción para el cliente: ' . $customer_id);
        Log::info('ID de precio: ' . $priceId);

        $subscription = $this->stripeService->getSubscriptionCustomer($customer_id, $priceId);

        Log::info('Suscripción obtenida: ' . $subscription);

        $result = $this->stripeService->cancelActiveSubscription($customer_id, $subscription['id']);

        return response()->json(['message' => 'Se ha cancelado la subscripción correctamente, serás redirigido en 5 segundos...'], 200);
    }
}
