<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\Request;

class CancellationController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('cancellation.index', [
            'customers' => [],
            'showSearchForm' => true
        ]);
    }

    /**
     * Buscar customer por email
     */
    public function searchByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            $email = $request->get('email');
            
            // Buscar el customer por email
            $result = $this->stripeService->searchCustomersByEmail($email);
            
            if (!$result['success']) {
                return view('cancellation.index', [
                    'customers' => [],
                    'showSearchForm' => true,
                    'error' => 'Error al buscar el cliente: ' . $result['error'],
                    'searchedEmail' => $email
                ]);
            }
            
            $customers = $result['data'] ?? [];
            
            if (empty($customers)) {
                return view('cancellation.index', [
                    'customers' => [],
                    'showSearchForm' => true,
                    'error' => 'No se encontró ningún cliente con el correo: ' . $email,
                    'searchedEmail' => $email
                ]);
            }
            
            return view('cancellation.index', [
                'customers' => $customers,
                'showSearchForm' => false,
                'searchedEmail' => $email
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al buscar cliente por email: ' . $e->getMessage());
            return view('cancellation.index', [
                'customers' => [],
                'showSearchForm' => true,
                'error' => 'Error inesperado al buscar el cliente.'
            ]);
        }
    }

    /**
     * Obtener más clientes con paginación (para AJAX)
     */
    public function loadMoreCustomers(Request $request)
    {
        try {
            $startingAfter = $request->get('starting_after');
            $limit = $request->get('limit', 50);
            
            $customersResult = $this->stripeService->getCustomerIds($limit, $startingAfter);
            
            if (!$customersResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $customersResult['error']
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'customers' => $customersResult['data'],
                'has_more' => $customersResult['has_more']
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al cargar más clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al cargar más clientes'
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(cr $cr)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(cr $cr)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, cr $cr)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(cr $cr)
    {
        //
    }
}
