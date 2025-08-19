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
     * Vista principal para mostrar customers
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('stripe.customers');
    }
}
