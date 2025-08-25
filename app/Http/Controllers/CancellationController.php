<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Services\BaremetricsService;
use Illuminate\Http\Request;

class CancellationController extends Controller
{
    protected $stripeService;
    protected $baremetricsService;

    public function __construct(StripeService $stripeService, BaremetricsService $baremetricsService)
    {
        $this->stripeService = $stripeService;
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('cancellation.index', [
            'customers' => [],
            'showSearchForm' => true,
            'barecancelJsUrl' => route('admin.cancellations.barecancel-js')
        ]);
    }

    /**
     * Buscar customer por email
     */
    public function searchByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255'
        ], [
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'Debe introducir un email válido.',
            'email.max' => 'El email no puede tener más de 255 caracteres.'
        ]);

        try {
            $email = trim(strtolower($request->get('email')));
            
            // Buscar el customer por email
            $result = $this->stripeService->searchCustomersByEmail($email);
            
            if (!$result['success']) {
                return back()->withInput()->with('error', 'Error al buscar el cliente: ' . $result['error']);
            }
            
            $customers = $result['data'] ?? [];
            
            if (empty($customers)) {
                return back()->withInput()->with('error', 'No se encontró ningún cliente con el correo: ' . $email);
            }
            
            return view('cancellation.index', [
                'customers' => $customers,
                'showSearchForm' => false,
                'searchedEmail' => $email,
                'barecancelJsUrl' => route('admin.cancellations.barecancel-js')
            ])->with('success', 'Se encontraron ' . count($customers) . ' cliente(s) con el email: ' . $email);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Laravel maneja automáticamente los errores de validación
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Error al buscar cliente por email: ' . $e->getMessage(), [
                'email' => $request->get('email'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withInput()->with('error', 'Error inesperado al buscar el cliente. Por favor, inténtelo de nuevo.');
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
     * Procesar cancelación manual
     */
    public function manualCancellation($customer_id)
    {
        $customer_id = $customer_id ?? null;
       return view('cancellation.manual', compact('customer_id'));
    }

    /**
     * Proxy the Baremetrics Barecancel JavaScript file to avoid CORS issues
     */
    public function proxyBarecancelJs()
    {
        try {
            $jsUrl = $this->baremetricsService->getBarecancelJsUrl();
            
            // Fetch the JavaScript content from the original URL
            $response = \Illuminate\Support\Facades\Http::timeout(30)->get($jsUrl);
            
            if ($response->successful()) {
                return response($response->body(), 200)
                    ->header('Content-Type', 'application/javascript; charset=utf-8')
                    ->header('Cache-Control', 'public, max-age=3600') // Cache for 1 hour
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type');
            }
            
            // If external request fails, return a fallback JavaScript
            return response('console.warn("Baremetrics Barecancel script could not be loaded");', 200)
                ->header('Content-Type', 'application/javascript; charset=utf-8');
                
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to proxy Barecancel JS', [
                'error' => $e->getMessage(),
                'url' => $jsUrl ?? 'unknown'
            ]);
            
            // Return a fallback JavaScript that sets up minimal functionality
            return response('console.warn("Baremetrics Barecancel script could not be loaded: ' . addslashes($e->getMessage()) . '");', 200)
                ->header('Content-Type', 'application/javascript; charset=utf-8');
        }
    }
}
