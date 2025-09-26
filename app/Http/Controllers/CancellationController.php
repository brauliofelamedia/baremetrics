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
use App\Models\CancellationToken;

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
    public function manualCancellation($customer_id = null, $subscription_id = null)
    {
       $customer_id = request()->get('customer_id', $customer_id);
       $subscription_id = request()->get('subscription_id', $subscription_id);
       
       // Obtener datos de la sesión
       $email = session('cancellation_email');
       $customer = session('cancellation_customer');
       $activeSubscriptions = session('cancellation_active_subscriptions', []);
       
       // Encontrar la suscripción seleccionada
       $selectedSubscription = null;
       foreach ($activeSubscriptions as $subscription) {
           if ($subscription['customer_id'] == $customer_id && $subscription['subscription_id'] == $subscription_id) {
               $selectedSubscription = $subscription;
               break;
           }
       }
       
       if (!$selectedSubscription && !empty($activeSubscriptions)) {
           // Si no se encontró la suscripción específica pero hay suscripciones activas,
           // tomamos la primera por defecto
           $selectedSubscription = $activeSubscriptions[0];
           $customer_id = $selectedSubscription['customer_id'];
           $subscription_id = $selectedSubscription['subscription_id'];
       }
       
       return view('cancellation.manual', [
           'subscription_id' => $subscription_id,
           'customer_id' => $customer_id,
           'email' => $email,
           'customer' => $customer,
           'selectedSubscription' => $selectedSubscription
       ]);
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
            // Enviar correo al usuario
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email
            ], function($message) use ($email) {
                $message->to($email)
                    ->subject('Verificación de cancelación de suscripción');
            });
            
            // Enviar copia a los administradores configurados
            $adminEmails = $this->getCancellationNotificationEmails();
            if (!empty($adminEmails)) {
                foreach ($adminEmails as $adminEmail) {
                    Mail::send('emails.cancellation-verification', [
                        'verificationUrl' => $verificationUrl,
                        'email' => $email,
                        'isAdminCopy' => true
                    ], function($message) use ($email, $adminEmail) {
                        $message->to($adminEmail)
                            ->subject('COPIA ADMIN - Solicitud de cancelación: ' . $email);
                    });
                }
            }
            
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

            // Verificamos si tiene suscripciones activas directamente en Stripe
            $hasActiveSubscriptions = false;
            foreach ($matchedCustomers as $customer) {
                try {
                    // Verificar directamente en Stripe todas las suscripciones del cliente
                    $allSubscriptions = \Stripe\Subscription::all([
                        'customer' => $customer['oid'],
                        'status' => 'all', // Obtenemos todas para verificar su estado
                        'limit' => 100
                    ]);
                    
                    // Verificamos si hay alguna suscripción activa o en periodo de prueba
                    foreach ($allSubscriptions->data as $subscription) {
                        if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                            $hasActiveSubscriptions = true;
                            break 2;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error al obtener suscripciones de Stripe: ' . $e->getMessage(), [
                        'customer_id' => $customer['oid'],
                        'email' => $email
                    ]);
                    
                    // Como respaldo, intentamos el método antiguo usando current_plans
                    if (isset($customer['current_plans']) && is_array($customer['current_plans']) && !empty($customer['current_plans'])) {
                        foreach ($customer['current_plans'] as $plan) {
                            if (isset($customer['oid']) && isset($plan['oid'])) {
                                $subscription = $this->stripeService->getSubscriptionCustomer($customer['oid'], $plan['oid']);
                                if ($subscription && isset($subscription['id'])) {
                                    $isCanceled = $this->stripeService->checkSubscriptionCancellationStatus($subscription['id']);
                                    if (!$isCanceled && $customer['is_canceled'] == false) {
                                        $hasActiveSubscriptions = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!$hasActiveSubscriptions) {
                return redirect()->back()->with('error', 'No tienes membresías activas de Stripe que puedas cancelar. Todas tus membresías ya se encuentran canceladas.');
            }

            // Generamos un token único
            $token = Str::random(64);
            
            \Log::info('Generando token de cancelación', [
                'email' => $email,
                'token' => substr($token, 0, 20) . '...',
                'expires_at' => Carbon::now()->addMinutes(15)->toDateTimeString()
            ]);
            
            // Almacenamos el token en la base de datos con una duración de 15 minutos
            $tokenRecord = CancellationToken::create([
                'token' => $token,
                'email' => $email,
                'expires_at' => Carbon::now()->addMinutes(15)
            ]);
            
            \Log::info('Token almacenado en base de datos', [
                'token_id' => $tokenRecord->id,
                'email' => $email
            ]);
            
            // También almacenamos en caché para compatibilidad con el método de verificación existente
            Cache::put('cancellation_token_' . $token, $email, Carbon::now()->addMinutes(15));
            
            \Log::info('Token almacenado en caché', [
                'email' => $email
            ]);
            
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
        
        // Obtenemos el token desde la base de datos
        $tokenRecord = CancellationToken::where('token', $token)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$tokenRecord) {
            return redirect()->route('home')->with('error', 'El token de verificación es inválido o ha expirado.');
        }
        
        $email = $tokenRecord->email;
        
        // Marcamos el token como usado
        $tokenRecord->markAsUsed();
        
        // También eliminamos de la caché para consistencia
        Cache::forget('cancellation_token_' . $token);
        
        // Redirigimos al proceso de cancelación
        return redirect()->route('cancellation.customer.ghl', ['email' => $email]);
    }

    public function cancellationCustomerGHL(Request $request)
    {
        $email = trim((string) $request->query('email', ''));

        if ($email === '') {
            return redirect()->back()->with('error', 'El parámetro email es obligatorio.');
        }

        $customers = $this->getCustomers($email);
        
        $customer = $customers[0] ?? ($customers['customers'][0] ?? null);
        
        // Array para almacenar las suscripciones activas
        $activeSubscriptions = [];

        if (!$customer) {
            return redirect()->back()->with('error', 'No se encontró ningún cliente con ese email.');
        }
        
        // Obtenemos todas las suscripciones directamente desde Stripe
        try {
            $allSubscriptions = \Stripe\Subscription::all([
                'customer' => $customer['oid'],
                'status' => 'all', // Obtener todas las suscripciones independientemente del estado
                'limit' => 100
            ]);
            
            // Filtramos las suscripciones activas
            foreach ($allSubscriptions->data as $subscription) {
                if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                    // Obtenemos información del plan desde la suscripción
                    $plan = [
                        'oid' => $subscription->plan->id,
                        'name' => $subscription->plan->nickname ?? 'Plan ' . $subscription->plan->amount/100 . ' ' . strtoupper($subscription->plan->currency),
                        'amount' => $subscription->plan->amount/100,
                        'currency' => strtoupper($subscription->plan->currency),
                        'interval' => $subscription->plan->interval,
                        'interval_count' => $subscription->plan->interval_count
                    ];
                    
                    $activeSubscriptions[] = [
                        'subscription' => $subscription,
                        'plan' => $plan,
                        'customer_id' => $customer['oid'],
                        'subscription_id' => $subscription->id
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error al obtener suscripciones de Stripe: ' . $e->getMessage(), [
                'customer_id' => $customer['oid'],
                'email' => $email
            ]);
            
            // Como respaldo, intentamos el método antiguo usando los planes de Baremetrics
            $plans = $customer['current_plans'] ?? [];
            foreach($plans as $plan){
                $subscription = $this->stripeService->getSubscriptionCustomer($customer['oid'], $plan['oid']);
                $isCanceled = false;
                
                if ($subscription && isset($subscription['id'])) {
                    $isCanceled = $this->stripeService->checkSubscriptionCancellationStatus($subscription['id']);
                    
                    // Si la suscripción no está cancelada, la agregamos al array de activas
                    if (!$isCanceled && $customer['is_canceled'] == false) {
                        $activeSubscriptions[] = [
                            'subscription' => $subscription,
                            'plan' => $plan,
                            'customer_id' => $customer['oid'],
                            'subscription_id' => $plan['oid']
                        ];
                    }
                }
            }
        }
        
        // Si no hay suscripciones activas, mostramos un mensaje
        if (empty($activeSubscriptions)) {
            return redirect()->back()->with('error', 'El cliente no tiene membresías activas de Stripe por cancelar. Todas las membresías ya se encuentran canceladas.');
        }
        
        // Almacenamos en sesión los datos necesarios para el proceso de cancelación
        session([
            'cancellation_email' => $email,
            'cancellation_customer' => $customer,
            'cancellation_active_subscriptions' => $activeSubscriptions
        ]);
        
        // Si hay solo una suscripción activa, redirigimos directamente a manualCancellation
        if (count($activeSubscriptions) === 1) {
            $subscription = $activeSubscriptions[0];
            return redirect()->route('cancellation.manual', [
                'customer_id' => $subscription['customer_id'],
                'subscription_id' => $subscription['subscription_id']
            ]);
        }
        
        // Si hay múltiples suscripciones, mostramos una vista para seleccionar cuál cancelar
        return view('cancellation.select_subscription', [
            'email' => $email,
            'customer' => $customer,
            'activeSubscriptions' => $activeSubscriptions
        ]);
    }

    public function cancellation()
    {
        return view('cancellation.form');
    }

    /**
     * Muestra la vista de administración de tokens de cancelación
     */
    public function adminTokens()
    {
        // Obtener todos los tokens activos de cancelación
        $activeTokens = $this->getActiveCancellationTokens();
        
        return view('admin.cancellation-tokens', [
            'activeTokens' => $activeTokens
        ]);
    }

    /**
     * Invalida un token de cancelación específico
     */
    public function invalidateToken(Request $request)
    {
        $token = $request->input('token');
        
        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token no válido'
            ], 400);
        }
        
        try {
            // Buscar y marcar el token como usado en la base de datos
            $tokenRecord = CancellationToken::where('token', $token)->first();
            
            if ($tokenRecord) {
                $tokenRecord->markAsUsed();
            }
            
            // También eliminar de la caché para consistencia
            Cache::forget('cancellation_token_' . $token);
            
            \Log::info('Token de cancelación invalidado por administrador', [
                'token' => $token,
                'admin' => auth()->user()->email ?? 'unknown'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Token invalidado correctamente'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al invalidar token: ' . $e->getMessage(), [
                'token' => $token,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al invalidar el token'
            ], 500);
        }
    }

    /**
     * Obtiene los correos electrónicos configurados para notificaciones de cancelación
     */
    private function getCancellationNotificationEmails()
    {
        $emailsString = env('CANCELLATION_NOTIFICATION_EMAILS', '');
        
        if (empty($emailsString)) {
            \Log::warning('No hay correos configurados para notificaciones de cancelación');
            return [];
        }
        
        // Dividir por comas y limpiar espacios
        $emails = array_map('trim', explode(',', $emailsString));
        
        // Filtrar correos vacíos y validar formato básico
        $validEmails = array_filter($emails, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        \Log::info('Correos de notificación de cancelación configurados', [
            'emails' => $validEmails,
            'total' => count($validEmails)
        ]);
        
        return array_values($validEmails);
    }

    /**
     * Obtiene todos los tokens activos de cancelación
     */
    private function getActiveCancellationTokens()
    {
        try {
            // Obtener tokens activos desde la base de datos
            $tokenRecords = CancellationToken::active()->orderBy('expires_at', 'asc')->get();
            
            $tokens = $tokenRecords->map(function ($tokenRecord) {
                return [
                    'token' => $tokenRecord->token,
                    'email' => $tokenRecord->email,
                    'expires_in_minutes' => $tokenRecord->remaining_minutes,
                    'expires_at' => $tokenRecord->expires_at
                ];
            })->toArray();
            
            return $tokens;
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener tokens activos: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Procesar cancelación de la suscripción (versión pública)
     */
    public function publicCancelSubscription(Request $request)
    {
        $customer_id = $request->get('customer_id');
        $subscription_id = $request->get('subscription_id');
        
        try {
            // Obtener el cliente y la suscripción directamente desde Stripe
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            
            // Verificar que el subscription pertenezca al customer
            if ($subscription->customer !== $customer_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta suscripción no pertenece al cliente especificado.'
                ], 400);
            }
            
            // Cancelar la suscripción
            $subscription->cancel();
            
            // Registrar la cancelación en nuestros sistemas si es necesario
            
            return response()->json([
                'success' => true,
                'message' => 'La suscripción ha sido cancelada correctamente.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al cancelar suscripción: ' . $e->getMessage(), [
                'customer_id' => $customer_id,
                'subscription_id' => $subscription_id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la suscripción: ' . $e->getMessage()
            ], 500);
        }
    }
}
