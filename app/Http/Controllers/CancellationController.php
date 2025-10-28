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
use App\Models\CancellationSurvey;

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
        if (empty($search)) {
            return [];
        }

        // Usar getCustomersByEmail para buscar en todas las fuentes
        return $this->baremetricsService->getCustomersByEmail($search);
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
                'expires_at' => Carbon::now()->addMinutes(30)->toDateTimeString()
            ]);
            
            // Almacenamos el token en la base de datos con una duración de 30 minutos
            $tokenRecord = CancellationToken::create([
                'token' => $token,
                'email' => $email,
                'expires_at' => Carbon::now()->addMinutes(30)
            ]);
            
            \Log::info('Token almacenado en base de datos', [
                'token_id' => $tokenRecord->id,
                'email' => $email
            ]);
            
            // También almacenamos en caché para compatibilidad con el método de verificación existente
            Cache::put('cancellation_token_' . $token, $email, Carbon::now()->addMinutes(30));
            
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
                ])->with('success', 'Se ha enviado un enlace de verificación a su correo electrónico. El enlace expirará en 30 minutos.');
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
            return redirect()->route('cancellation.form')->with('error', 'El token de verificación es inválido o ha expirado.');
        }

        // Obtenemos el token desde la base de datos
        $tokenRecord = CancellationToken::where('token', $token)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return redirect()->route('cancellation.form')->with('error', 'El token de verificación es inválido o ha expirado.');
        }

        $email = $tokenRecord->email;

        // Marcamos el token como usado
        $tokenRecord->markAsUsed();

        // También eliminamos de la caché para consistencia
        Cache::forget('cancellation_token_' . $token);

        // Obtener el cliente usando el email
        $customers = $this->getCustomers($email);

        if (empty($customers) || !isset($customers[0])) {
            return redirect()->route('cancellation.form')->with('error', 'No se encontró información del cliente para proceder con la cancelación.');
        }

        $customer = $customers[0];
        $customerId = $customer['oid'] ?? null;

        if (!$customerId) {
            return redirect()->route('cancellation.form')->with('error', 'No se pudo obtener la identificación del cliente.');
        }

        // Almacenar información del cliente en la sesión para evitar búsquedas posteriores
        session([
            'cancellation_customer' => $customer,
            'cancellation_customer_id' => $customerId,
            'cancellation_email' => $email
        ]);

        // Redirigir directamente a la survey con los datos del cliente
        return redirect()->route('cancellation.survey', ['customer_id' => $customerId]);
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
        
        // Eliminar duplicados (importante para evitar envíos múltiples)
        $validEmails = array_unique($validEmails);
        
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
    
    public function surveyCancellation($customer_id)
    {
        // Obtener información del cliente de la sesión (almacenada durante la verificación del token)
        $customer = session('cancellation_customer');

        // Verificar que el customer_id coincida con el de la sesión
        if (!$customer || ($customer['oid'] ?? null) !== $customer_id) {
            // Si no hay información en la sesión o no coincide, intentar buscar (como fallback)
            $customer = null;
            try {
                // Buscar el cliente por su OID en todas las fuentes (solo como fallback)
                $sources = $this->baremetricsService->getSources();
                if ($sources) {
                    $sourceIds = [];
                    if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
                        $sourceIds = array_column($sources['sources'], 'id');
                    } elseif (is_array($sources)) {
                        $sourceIds = array_column($sources, 'id');
                    }

                    foreach ($sourceIds as $sourceId) {
                        // Buscar en las primeras 10 páginas para encontrar el cliente
                        for ($page = 1; $page <= 10; $page++) {
                            $response = $this->baremetricsService->getCustomers($sourceId, '', $page);
                            if ($response && isset($response['customers'])) {
                                foreach ($response['customers'] as $cust) {
                                    if (isset($cust['oid']) && $cust['oid'] === $customer_id) {
                                        $customer = $cust;
                                        break 3; // Salir de todos los bucles
                                    }
                                }
                            } else {
                                // Si no hay respuesta o no hay customers, salir de este source
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Error obteniendo datos del cliente para survey: ' . $e->getMessage());
            }
        }

        return view('cancellation.survey', compact('customer_id', 'customer'));
    }

    public function surveyCancellationSave(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|string',
            'email' => 'nullable|email|max:255',
            'reason' => 'required|string',
            'additional_comments' => 'nullable|string|max:1000',
        ]);

        $customer_id = $request->input('customer_id');
        $email = $request->input('email');
        $reason = $request->input('reason');
        $additional_comments = $request->input('additional_comments');

        \Log::info('Procesando survey de cancelación', [
            'customer_id' => $customer_id,
            'email' => $email,
            'reason' => $reason
        ]);

        // Obtener información del cliente de la sesión primero
        $customer = session('cancellation_customer');
        
        // Si el email no vino en el request, intentar obtenerlo del customer
        if (empty($email) && $customer && isset($customer['email'])) {
            $email = $customer['email'];
            \Log::info('Email obtenido del customer en sesión', [
                'customer_id' => $customer_id,
                'email' => $email
            ]);
        }

        // Verificar que el customer_id coincida
        if (!$customer || ($customer['oid'] ?? null) !== $customer_id) {
            // Si no hay información en la sesión o no coincide, buscar el email si existe
            $customer = null;
            
            // Si tenemos email, buscar por email en Baremetrics
            if ($email) {
                \Log::info('Buscando cliente por email en Baremetrics', [
                    'customer_id' => $customer_id,
                    'email' => $email
                ]);
                
                try {
                    $customers = $this->baremetricsService->getCustomersByEmail($email);
                    if (!empty($customers)) {
                        // Buscar el que coincida con el customer_id
                        foreach ($customers as $cust) {
                            if (isset($cust['oid']) && $cust['oid'] === $customer_id) {
                                $customer = $cust;
                                \Log::info('Cliente encontrado por email', [
                                    'customer_id' => $customer_id,
                                    'customer_name' => $cust['name'] ?? 'N/A',
                                    'customer_email' => $cust['email'] ?? 'N/A'
                                ]);
                                break;
                            }
                        }
                        
                        // Si no coincide exactamente, usar el primero encontrado
                        if (!$customer && !empty($customers)) {
                            $customer = is_array($customers) && isset($customers[0]) ? $customers[0] : $customers;
                            \Log::info('Usando primer cliente encontrado por email', [
                                'customer_id' => $customer_id,
                                'found_customer_id' => $customer['oid'] ?? 'N/A'
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error buscando cliente por email: ' . $e->getMessage());
                }
            }
            
            // Si no se encontró por email, crear un customer básico con el customer_id
            if (!$customer) {
                $customer = [
                    'oid' => $customer_id,
                    'email' => $email,
                    'name' => 'Usuario',
                ];
                \Log::info('Usando customer_id directamente (no encontrado en Baremetrics)', [
                    'customer_id' => $customer_id
                ]);
            }
            
            // Si aún no tenemos email, intentar obtenerlo de Stripe
            if (empty($email) && $customer_id) {
                try {
                    $stripeCustomer = \Stripe\Customer::retrieve($customer_id);
                    if ($stripeCustomer && isset($stripeCustomer->email)) {
                        $email = $stripeCustomer->email;
                        $customer['email'] = $email;
                        \Log::info('Email obtenido de Stripe', [
                            'customer_id' => $customer_id,
                            'email' => $email
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('No se pudo obtener email de Stripe', [
                        'customer_id' => $customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Obtener suscripciones activas desde Stripe directamente
        $activeSubscriptions = [];
        try {
            \Log::info('Consultando suscripciones en Stripe', [
                'customer_id' => $customer_id
            ]);
            
            $allSubscriptions = \Stripe\Subscription::all([
                'customer' => $customer_id,  // Usar el customer_id directamente
                'status' => 'all',
                'limit' => 100
            ]);

            foreach ($allSubscriptions->data as $subscription) {
                if ($subscription->status === 'active' || $subscription->status === 'trialing') {
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
                        'customer_id' => $customer_id,
                        'subscription_id' => $subscription->id,
                        'status' => $subscription->status,
                        'current_period_start' => $subscription->current_period_start,
                        'current_period_end' => $subscription->current_period_end
                    ];
                }
            }

            \Log::info('Suscripciones encontradas en Stripe', [
                'customer_id' => $customer_id,
                'subscriptions_count' => count($activeSubscriptions),
                'all_subscriptions_count' => count($allSubscriptions->data)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error obteniendo suscripciones de Stripe: ' . $e->getMessage(), [
                'customer_id' => $customer_id,
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Guardar el survey en la base de datos
        try {
            CancellationSurvey::create([
                'customer_id' => $customer_id,
                'email' => $email,
                'reason' => $reason,
                'additional_comments' => $additional_comments,
            ]);

            \Log::info('Survey de cancelación guardado', [
                'customer_id' => $customer_id,
                'email' => $email,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            \Log::error('Error guardando survey de cancelación: ' . $e->getMessage(), [
                'customer_id' => $customer_id,
                'email' => $email,
                'reason' => $reason
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la información del survey: ' . $e->getMessage()
            ], 500);
        }

        // Actualizar custom fields en Baremetrics con el motivo y comentarios de cancelación
        try {
            \Log::info('Actualizando custom fields en Baremetrics', [
                'customer_id' => $customer_id,
                'reason' => $reason,
                'has_comments' => !empty($additional_comments)
            ]);
            
            $baremetricsData = [
                'cancellation_reason' => $reason,
            ];
            
            if (!empty($additional_comments)) {
                $baremetricsData['cancellation_comments'] = $additional_comments;
            }
            
            $updateResult = $this->baremetricsService->updateCustomerAttributes($customer_id, $baremetricsData);
            
            if ($updateResult) {
                \Log::info('Custom fields de Baremetrics actualizados exitosamente', [
                    'customer_id' => $customer_id,
                    'updated_fields' => array_keys($baremetricsData)
                ]);
            } else {
                \Log::warning('No se pudieron actualizar los custom fields en Baremetrics', [
                    'customer_id' => $customer_id
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error actualizando custom fields en Baremetrics: ' . $e->getMessage(), [
                'customer_id' => $customer_id,
                'reason' => $reason,
                'trace' => $e->getTraceAsString()
            ]);
            // No detenemos el flujo si falla la actualización de Baremetrics
        }

        // Registrar el motivo de cancelación en Barecancel Insights
        try {
            \Log::info('Intentando registrar motivo en Barecancel Insights', [
                'customer_id' => $customer_id,
                'reason' => $reason
            ]);
            
            $barecancelResult = $this->baremetricsService->recordCancellationReason(
                $customer_id, 
                $reason, 
                $additional_comments
            );
            
            if ($barecancelResult) {
                \Log::info('Motivo de cancelación registrado en Barecancel', [
                    'customer_id' => $customer_id,
                    'reason' => $reason,
                    'barecancel_response' => $barecancelResult
                ]);
            } else {
                \Log::warning('No se pudo registrar el motivo en Barecancel (puede que la API no esté disponible)', [
                    'customer_id' => $customer_id,
                    'reason' => $reason
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error registrando motivo en Barecancel: ' . $e->getMessage(), [
                'customer_id' => $customer_id,
                'reason' => $reason,
                'trace' => $e->getTraceAsString()
            ]);
            // No detenemos el flujo si falla el registro en Barecancel
        }

        // Actualizar el motivo de cancelación en GoHighLevel
        if ($email) {
            try {
                \Log::info('Intentando actualizar motivo en GHL', [
                    'customer_id' => $customer_id,
                    'email' => $email,
                    'reason' => $reason
                ]);
                
                // Buscar el contacto en GHL por email
                $ghlService = app(\App\Services\GoHighLevelService::class);
                $ghlContact = $ghlService->getContactsByExactEmail($email);
                
                if (empty($ghlContact['contacts'])) {
                    // Intentar con búsqueda contains
                    $ghlContact = $ghlService->getContacts($email);
                }
                
                if (!empty($ghlContact['contacts'])) {
                    $contactId = $ghlContact['contacts'][0]['id'];
                    
                    // Preparar los custom fields para GHL
                    $customFields = [
                        'UhyA0ol6XoETLRA5jsZa' => $reason,  // Campo "Motivo de cancelacion"
                    ];
                    
                    // Agregar comentarios si existen
                    if (!empty($additional_comments)) {
                        $customFields['zYi50QSDZC6eGqoRH8Zm'] = $additional_comments;  // Campo "Comentarios de cancelacion"
                    }
                    
                    // Actualizar los custom fields en GHL usando el método específico
                    $ghlResult = $ghlService->updateContactCustomFields($contactId, $customFields);
                    
                    if ($ghlResult) {
                        \Log::info('Motivo de cancelación actualizado en GHL', [
                            'customer_id' => $customer_id,
                            'email' => $email,
                            'contact_id' => $contactId,
                            'reason' => $reason,
                            'has_comments' => !empty($additional_comments)
                        ]);
                    } else {
                        \Log::warning('No se pudo actualizar el motivo en GHL', [
                            'customer_id' => $customer_id,
                            'email' => $email,
                            'contact_id' => $contactId
                        ]);
                    }
                } else {
                    \Log::warning('Contacto no encontrado en GHL para actualizar motivo', [
                        'customer_id' => $customer_id,
                        'email' => $email
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Error actualizando motivo en GHL: ' . $e->getMessage(), [
                    'customer_id' => $customer_id,
                    'email' => $email,
                    'reason' => $reason,
                    'trace' => $e->getTraceAsString()
                ]);
                // No detenemos el flujo si falla la actualización en GHL
            }
        }

        // Cancelar suscripciones en Stripe y Baremetrics
        $cancellationResults = [];
        $hasErrors = false;

        if ($customer && !empty($activeSubscriptions)) {
            foreach ($activeSubscriptions as $subscriptionData) {
                $subscriptionId = $subscriptionData['subscription_id'];

                // Cancelar en Stripe
                try {
                    $stripeResult = $this->stripeService->cancelActiveSubscription($customer_id, $subscriptionId);
                    if ($stripeResult['success']) {
                        $cancellationResults[] = [
                            'subscription_id' => $subscriptionId,
                            'stripe' => 'success',
                            'stripe_details' => $stripeResult['data']
                        ];
                        \Log::info('Suscripción cancelada en Stripe', [
                            'customer_id' => $customer_id,
                            'subscription_id' => $subscriptionId
                        ]);
                    } else {
                        $cancellationResults[] = [
                            'subscription_id' => $subscriptionId,
                            'stripe' => 'error',
                            'stripe_error' => $stripeResult['error']
                        ];
                        $hasErrors = true;
                        \Log::error('Error cancelando suscripción en Stripe', [
                            'customer_id' => $customer_id,
                            'subscription_id' => $subscriptionId,
                            'error' => $stripeResult['error']
                        ]);
                    }
                } catch (\Exception $e) {
                    $cancellationResults[] = [
                        'subscription_id' => $subscriptionId,
                        'stripe' => 'error',
                        'stripe_error' => $e->getMessage()
                    ];
                    $hasErrors = true;
                    \Log::error('Excepción cancelando suscripción en Stripe', [
                        'customer_id' => $customer_id,
                        'subscription_id' => $subscriptionId,
                        'error' => $e->getMessage()
                    ]);
                }

                // Cancelar en Baremetrics (si tenemos el OID de la suscripción)
                if (isset($subscriptionData['subscription']['oid'])) {
                    try {
                        // Necesitamos encontrar el source_id para este cliente
                        $sources = $this->baremetricsService->getSources();
                        if ($sources) {
                            $sourceIds = [];
                            if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
                                $sourceIds = array_column($sources['sources'], 'id');
                            } elseif (is_array($sources)) {
                                $sourceIds = array_column($sources, 'id');
                            }

                            // Intentar cancelar en cada source (usualmente solo hay uno)
                            foreach ($sourceIds as $sourceId) {
                                $baremetricsResult = $this->baremetricsService->deleteSubscription($sourceId, $subscriptionData['subscription']['oid']);
                                if ($baremetricsResult) {
                                    // Encontrar el resultado correspondiente y actualizar
                                    foreach ($cancellationResults as &$result) {
                                        if ($result['subscription_id'] === $subscriptionId) {
                                            $result['baremetrics'] = 'success';
                                            break;
                                        }
                                    }
                                    \Log::info('Suscripción cancelada en Baremetrics', [
                                        'customer_id' => $customer_id,
                                        'subscription_id' => $subscriptionId,
                                        'source_id' => $sourceId
                                    ]);
                                    break; // Solo necesitamos cancelar en un source
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Encontrar el resultado correspondiente y actualizar
                        foreach ($cancellationResults as &$result) {
                            if ($result['subscription_id'] === $subscriptionId) {
                                $result['baremetrics'] = 'error';
                                $result['baremetrics_error'] = $e->getMessage();
                                break;
                            }
                        }
                        $hasErrors = true;
                        \Log::error('Excepción cancelando suscripción en Baremetrics', [
                            'customer_id' => $customer_id,
                            'subscription_id' => $subscriptionId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        } else {
            // No se encontró cliente o no tiene suscripciones activas
            $hasErrors = true;
            \Log::warning('No se pudo procesar cancelación: cliente no encontrado o sin suscripciones', [
                'customer_id' => $customer_id,
                'email' => $email,
                'customer_found' => $customer ? true : false,
                'active_subscriptions_count' => isset($activeSubscriptions) ? count($activeSubscriptions) : 0
            ]);
        }

        // Preparar respuesta
        if ($hasErrors) {
            $successMessage = 'Cancelación procesada parcialmente. Algunas suscripciones pudieron no cancelarse correctamente.';
        } else {
            $successMessage = 'Todas las suscripciones han sido canceladas correctamente.';
        }

        return view('cancellation.result', [
            'success' => !$hasErrors,
            'message' => $successMessage,
            'data' => [
                'customer_id' => $customer_id,
                'email' => $email,
                'reason' => $reason,
                'additional_comments' => $additional_comments,
                'subscriptions_cancelled' => count($cancellationResults),
                'cancellation_details' => $cancellationResults
            ],
            'hasErrors' => $hasErrors
        ]);
    }
}
