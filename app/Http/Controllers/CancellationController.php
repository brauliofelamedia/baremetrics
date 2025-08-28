<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Services\BaremetricsService;
use Illuminate\Http\Request;
use Log;
use Cache;

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
    public function index(Request $request)
    {
        // Obtener el email desde la query string: ?email=...
        $email = trim((string) $request->query('email', ''));

        if ($email !== '') {
            $customers = $this->getCustomers($email);

            // Algunas implementaciones devuelven ['customers' => [...]]; soportamos ambas estructuras
            if (is_array($customers) && isset($customers['customers']) && is_array($customers['customers'])) {
                $customers = $customers['customers'];
            } elseif (!is_array($customers)) {
                $customers = [];
            }

            // Cuando se hizo una búsqueda, ocultamos el formulario auxiliar de "nueva búsqueda"
            $showSearchForm = false;
        } else {
            $customers = [];
            $showSearchForm = true;
        }

        return view('cancellation.index', [
            'customers' => $customers,
            'showSearchForm' => $showSearchForm,
            'searchedEmail' => $email,
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
            $customers = $this->getCustomers($email);

            $result = [
                'success' => false,
                'data' => [],
                'error' => ''
            ];

            $matchedCustomers = array_filter($customers, function ($customer) use ($email) {
                return isset($customer['email']) && strtolower(trim($customer['email'])) === $email;
            });

            if ($matchedCustomers) {
                return view('cancellation.index', [
                    'customers' => array_values($matchedCustomers),
                    'showSearchForm' => false,
                    'searchedEmail' => $email,
                ])->with('success', 'Se encontraron ' . count($matchedCustomers) . ' cliente(s) con el email: ' . $email);
            } else {
                return view('cancellation.index', [
                    'customers' => [],
                    'showSearchForm' => false,
                    'searchedEmail' => $email,
                ])->with('error', 'No se encontró ningún cliente con ese email.');
            }
            
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
     * Procesar cancelación manual
     */
    public function manualCancellation($customer_id, $subscription_id)
    {
       $customer_id = request()->get('customer_id', $customer_id);
       $subscription_id = request()->get('subscription_id', $subscription_id);

       return view('cancellation.manual', compact('subscription_id', 'customer_id'));
    }

    private function getCustomers(string $search = '')
    {
        $sources = $this->baremetricsService->getSources();

        // Normalizar la estructura: la respuesta puede venir como ['sources' => [...]]
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

        //Iteramos la lista de sources para obtener los clientes
        $customersExtract = [];
        foreach ($sourceIds as $sourceId) {
            $customers = $this->baremetricsService->getCustomers($sourceId, $search);

            if ($customers) {
                $customersExtract = array_merge($customersExtract, $customers);
            }
        }
        return $customersExtract;
    }
}
