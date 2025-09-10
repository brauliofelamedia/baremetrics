<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use App\Services\BaremetricsService;
use Illuminate\Http\Request;
use Log;
use Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

    /**
     * Envía el correo electrónico con el enlace de verificación
     * 
     * @param string $email Correo electrónico del destinatario
     * @param string $verificationUrl URL para verificar la cancelación
     * @return bool Indica si el correo se envió correctamente
     */
    private function sendVerificationEmail(string $email, string $verificationUrl)
    {
        try {
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email
            ], function($message) use ($email) {
                $message->to($email)
                    ->subject('Verificación de cancelación de suscripción');
            });
            
            return !Mail::failures();
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de verificación: ' . $e->getMessage(), [
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Envía un correo de verificación con un token mágico para la cancelación
     */
    public function sendCancellationVerification(Request $request)
    {
        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return redirect()->back()->with('error', 'El parámetro email es obligatorio.');
        }

        try {
            // Verificamos si el email existe en nuestros sistemas
            $customers = $this->getCustomers($email);
            
            // Verificamos que haya clientes con ese email
            if (is_array($customers) && isset($customers['customers']) && is_array($customers['customers'])) {
                $customersArray = $customers['customers'];
            } elseif (is_array($customers)) {
                $customersArray = $customers;
            } else {
                $customersArray = [];
            }

            $matchedCustomers = array_filter($customersArray, function ($customer) use ($email) {
                return isset($customer['email']) && strtolower(trim($customer['email'])) === strtolower(trim($email));
            });

            if (empty($matchedCustomers)) {
                return redirect()->back()->with('error', 'No se encontró ningún cliente con ese email.');
            }

            // Generamos un token único
            $token = Str::random(64);
            
            // Almacenamos el token en la caché con una duración de 15 minutos
            Cache::put('cancellation_token_' . $token, $email, Carbon::now()->addMinutes(15));
            
            // Generamos la URL de verificación
            $verificationUrl = route('cancellation.verify', ['token' => $token]);
            
            // Enviamos el correo con el enlace de verificación
            $mailSent = $this->sendVerificationEmail($email, $verificationUrl);
            
            if ($mailSent) {
                return view('cancellation.verification-sent', [
                    'email' => $email
                ])->with('success', 'Se ha enviado un enlace de verificación a su correo electrónico. El enlace expirará en 15 minutos.');
            } else {
                return view('cancellation.verification-sent', [
                    'email' => $email
                ])->with('error', 'No se pudo enviar el correo de verificación. Por favor, intente nuevamente.');
            }
            
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de verificación: ' . $e->getMessage(), [
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            
            return view('cancellation.verification-sent', [
                'email' => $email
            ])->with('error', 'Error inesperado al enviar el correo de verificación. Por favor, inténtelo de nuevo.');
        }
    }

    /**
     * Verifica el token mágico y redirige al proceso de cancelación
     */
    public function verifyCancellationToken(Request $request)
    {
        $token = $request->query('token', '');
        
        if (empty($token)) {
            return redirect()->route('home')->with('error', 'El token de verificación es inválido o ha expirado.');
        }
        
        // Obtenemos el email asociado al token desde la caché
        $email = Cache::get('cancellation_token_' . $token);
        
        if (empty($email)) {
            return redirect()->route('home')->with('error', 'El token de verificación es inválido o ha expirado.');
        }
        
        // Eliminamos el token usado para evitar reuso
        Cache::forget('cancellation_token_' . $token);
        
        // Redirigimos al proceso de cancelación
        return redirect()->route('cancellation.customer.ghl', ['email' => $email]);
    }

    public function cancellationCustomerGHL(Request $request)
    {
        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return view('cancellation.index', [
                'showSearchForm' => true
            ])->with('error', 'El parámetro email es obligatorio.');
        }

        $customers = $this->getCustomers($email);
        
        $customer = $customers['customers'][0] ?? null;
        $plans = $customer['current_plans'] ?? [];

        if (!$customer) {
            return view('cancellation.index', [
                'showSearchForm' => false,
                'searchedEmail' => $email
            ])->with('error', 'No se encontró ningún cliente con ese email.');
        }
        
        foreach($plans as $plan){

            $subscription = $this->stripeService->getSubscriptionCustomer($customer['oid'],$plan['oid']);
            $isCanceled = false;
            if ($subscription && isset($subscription['id'])) {
                $isCanceled = $this->stripeService->checkSubscriptionCancellationStatus($subscription['id']);
            }

            if ($customer['is_canceled'] == false && !$isCanceled) {
                return redirect()->route('admin.cancellations.manual', ['customer_id' => $customer['oid'],'subscription_id' => $plan['oid']])->with('message', 'Redirigiendo para cancelar el plan.');
            }
        }

        return view('cancellation.no-plans', [
            'email' => $email,
            'customer' => $customer
        ])->with('message', 'El cliente no tiene planes de Stripe por cancelar');
    }

    public function cancellation()
    {
        return view('cancellation.form');
    }
}
