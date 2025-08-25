<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;

class StripeController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Obtener todos los customer IDs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getCustomers(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 100);
        $startingAfter = $request->get('starting_after');

        $result = $this->stripeService->getCustomerIds($limit, $startingAfter);

        return response()->json($result);
    }

    /**
     * Obtener todos los customers sin límite
     *
     * @return JsonResponse
     */
    public function getAllCustomers(): JsonResponse
    {
        $result = $this->stripeService->getAllCustomerIds();

        return response()->json($result);
    }

    /**
     * Obtener un customer específico
     *
     * @param string $customerId
     * @return JsonResponse
     */
    public function getCustomer($customerId): JsonResponse
    {
        $result = $this->stripeService->getCustomer($customerId);

        return response()->json($result);
    }

    /**
     * Buscar customers por email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $result = $this->stripeService->searchCustomersByEmail($request->email);

        return response()->json($result);
    }

    /**
     * Obtener la clave publishable
     *
     * @return JsonResponse
     */
    public function getPublishableKey(): JsonResponse
    {
        return response()->json([
            'publishable_key' => $this->stripeService->getPublishableKey()
        ]);
    }

    /**
     * Cargar más customers vía AJAX
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function loadMoreCustomers(Request $request): JsonResponse
    {
        $startingAfter = $request->get('starting_after');
        $limit = $request->get('limit', 50);

        $result = $this->stripeService->getCustomerIds($limit, $startingAfter);

        if ($result['success']) {
            $result['pagination'] = [
                'has_more' => $result['has_more'] ?? false,
                'starting_after' => !empty($result['data']) ? end($result['data'])['id'] : null,
                'current_count' => count($result['data'])
            ];
        }

        return response()->json($result);
    }

    /**
     * Vista principal para mostrar customers
     *
     * @return \Illuminate\View\View
     */
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
}
