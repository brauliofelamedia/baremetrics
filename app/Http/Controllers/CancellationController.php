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
use App\Models\CancellationTracking;

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
     * Obtener el ID de Stripe del cliente
     * 
     * @param string $customer_id ID del cliente (puede ser de Stripe o Baremetrics)
     * @param string|null $email Email del cliente para búsqueda alternativa
     * @return string|null ID de Stripe del cliente o null si no se encuentra
     */
    private function getStripeCustomerId($customer_id, $email = null)
    {
        // Si el customer_id ya es un ID de Stripe (empieza con cus_)
        if (!empty($customer_id) && strpos($customer_id, 'cus_') === 0) {
            return $customer_id;
        }

        $stripeCustomerId = null;

        // Intentar buscar por email en Stripe
        if (!empty($email)) {
            try {
                $stripeCustomers = \Stripe\Customer::all([
                    'email' => $email,
                    'limit' => 1
                ]);

                if (!empty($stripeCustomers->data)) {
                    $stripeCustomerId = $stripeCustomers->data[0]->id;
                    \Log::info('ID de Stripe encontrado por email', [
                        'email' => $email,
                        'stripe_customer_id' => $stripeCustomerId
                    ]);
                    return $stripeCustomerId;
                }
            } catch (\Exception $e) {
                \Log::warning('Error buscando cliente en Stripe por email', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Intentar buscar usando el customer_id directamente (por si acaso es un ID de Stripe que no empieza con cus_)
        if (!empty($customer_id)) {
            try {
                $stripeCustomer = \Stripe\Customer::retrieve($customer_id);
                if ($stripeCustomer && isset($stripeCustomer->id)) {
                    $stripeCustomerId = $stripeCustomer->id;
                    \Log::info('ID de Stripe encontrado usando customer_id directamente', [
                        'customer_id' => $customer_id,
                        'stripe_customer_id' => $stripeCustomerId
                    ]);
                    return $stripeCustomerId;
                }
            } catch (\Exception $e) {
                // No es un ID de Stripe válido, continuar
                \Log::debug('customer_id no es un ID de Stripe válido', [
                    'customer_id' => $customer_id
                ]);
            }
        }

        \Log::warning('No se pudo obtener ID de Stripe del cliente', [
            'customer_id' => $customer_id,
            'email' => $email
        ]);

        return null;
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

    /**
     * Procesar cancelación con embed de Baremetrics
     */
    public function embedCancellation($customer_id = null, $subscription_id = null)
    {
       $customer_id = request()->get('customer_id', $customer_id);
       $subscription_id = request()->get('subscription_id', $subscription_id);
       
       \Log::info('embedCancellation llamado', [
           'customer_id' => $customer_id,
           'subscription_id' => $subscription_id
       ]);
       
       // Obtener datos de la sesión
       $email = session('cancellation_email');
       $customer = session('cancellation_customer');
       $activeSubscriptions = session('cancellation_active_subscriptions', []);
       
       // Encontrar la suscripción seleccionada
       $selectedSubscription = null;
       if (!empty($activeSubscriptions) && !empty($subscription_id)) {
           foreach ($activeSubscriptions as $subscription) {
               // Buscar por subscription_id o baremetrics_subscription_oid
               $subId = $subscription['subscription_id'] ?? null;
               $bmSubId = $subscription['baremetrics_subscription_oid'] ?? null;
               
               if (($subId && $subId == $subscription_id) || 
                   ($bmSubId && $bmSubId == $subscription_id)) {
                   $selectedSubscription = $subscription;
                   \Log::info('Suscripción encontrada en sesión', [
                       'subscription_id' => $subscription_id,
                       'encontrada' => true
                   ]);
                   break;
               }
           }
       }
       
       if (!$selectedSubscription && !empty($activeSubscriptions)) {
           // Si no se encontró la suscripción específica pero hay suscripciones activas,
           // tomamos la primera por defecto
           $selectedSubscription = $activeSubscriptions[0];
           $customer_id = $selectedSubscription['customer_id'] ?? $customer_id;
           $subscription_id = $selectedSubscription['subscription_id'] ?? 
                             $selectedSubscription['baremetrics_subscription_oid'] ?? 
                             $subscription_id;
           \Log::info('Usando primera suscripción de la sesión como fallback', [
               'subscription_id' => $subscription_id
           ]);
       }
       
       // Si aún no tenemos subscription_id pero tenemos subscription de Baremetrics, usar ese
       if (empty($subscription_id) && $selectedSubscription) {
           $subscription_id = $selectedSubscription['baremetrics_subscription_oid'] ?? 
                            $selectedSubscription['subscription_id'] ?? 
                            $subscription_id;
       }
       
       \Log::info('embedCancellation - Datos finales para el embed', [
           'customer_id' => $customer_id,
           'subscription_id' => $subscription_id,
           'has_selectedSubscription' => !empty($selectedSubscription),
           'email' => $email
       ]);
       
       return view('cancellation.embed', [
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
     * @param string $flowType Tipo de flujo ('survey' o 'embed')
     * @return bool Indica si el correo se envió correctamente
     */
    private function sendVerificationEmail(string $email, string $verificationUrl, string $flowType = 'survey')
    {
        try {
            \Log::info('Iniciando envío de correo de verificación', [
                'email' => $email,
                'flowType' => $flowType,
                'mail_driver' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
                'mail_port' => config('mail.mailers.smtp.port')
            ]);
            
            // Enviar correo al usuario
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email,
                'flowType' => $flowType
            ], function($message) use ($email, $flowType) {
                $subject = $flowType === 'embed' 
                    ? 'Verificación de cancelación de suscripción (Embed)'
                    : 'Verificación de cancelación de suscripción';
                $message->to($email)
                    ->subject($subject);
            });
            
            \Log::info('Correo principal enviado', [
                'email' => $email
            ]);
            
            // Enviar copia a los administradores configurados
            $adminEmails = $this->getCancellationNotificationEmails();
            \Log::info('Verificando correos de administradores', [
                'admin_emails_count' => count($adminEmails),
                'admin_emails' => $adminEmails,
                'admin_emails_empty_check' => empty($adminEmails),
                'user_email' => $email
            ]);
            
            if (!empty($adminEmails) && count($adminEmails) > 0) {
                \Log::info('Iniciando envío de correos a administradores', [
                    'total_emails' => count($adminEmails),
                    'emails' => $adminEmails
                ]);
                $adminEmailsSent = [];
                $adminEmailsFailed = [];
                
                foreach ($adminEmails as $adminEmail) {
                    try {
                        Mail::send('emails.cancellation-verification', [
                            'verificationUrl' => $verificationUrl,
                            'email' => $email,
                            'isAdminCopy' => true,
                            'flowType' => $flowType
                        ], function($message) use ($email, $adminEmail, $flowType) {
                            $subject = $flowType === 'embed'
                                ? 'COPIA ADMIN - Solicitud de cancelación (Embed): ' . $email
                                : 'COPIA ADMIN - Solicitud de cancelación: ' . $email;
                            $message->to($adminEmail)
                                ->subject($subject);
                        });
                        
                        $adminEmailsSent[] = $adminEmail;
                        \Log::info('Correo de administrador enviado exitosamente', [
                            'admin_email' => $adminEmail,
                            'user_email' => $email
                        ]);
                    } catch (\Exception $adminMailError) {
                        $adminEmailsFailed[] = $adminEmail;
                        \Log::error('Error al enviar correo a administrador', [
                            'admin_email' => $adminEmail,
                            'user_email' => $email,
                            'error' => $adminMailError->getMessage()
                        ]);
                    }
                }
                
                \Log::info('Correos de administradores procesados', [
                    'admin_emails_total' => count($adminEmails),
                    'admin_emails_sent' => count($adminEmailsSent),
                    'admin_emails_failed' => count($adminEmailsFailed),
                    'admin_emails_sent_list' => $adminEmailsSent,
                    'admin_emails_failed_list' => $adminEmailsFailed
                ]);
            } else {
                \Log::warning('No se enviaron correos a administradores - lista vacía o no configurada', [
                    'admin_emails_count' => count($adminEmails),
                    'admin_emails' => $adminEmails
                ]);
            }
            
            // Verificar si hubo errores en el envío de correos a administradores
            $success = true;
            if (isset($adminEmailsFailed) && !empty($adminEmailsFailed)) {
                \Log::warning('Algunos correos de administradores fallaron', [
                    'failed_emails' => $adminEmailsFailed
                ]);
                // No marcamos como fallido completamente si el correo principal se envió
            }
            
            \Log::info('Proceso de envío de correos completado', [
                'user_email' => $email,
                'success' => $success
            ]);
            
            return $success;
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de verificación: ' . $e->getMessage(), [
                'email' => $email,
                'flowType' => $flowType,
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
        // Detectar si se está usando el flujo embed mediante parámetro GET
        $useEmbed = $request->has('embed') && $request->query('embed') == '1';
        $flowType = $useEmbed ? 'embed' : 'survey';

        \Log::info('Iniciando solicitud de verificación de cancelación', [
            'email' => $email,
            'has_embed_param' => $request->has('embed'),
            'embed_value' => $request->query('embed'),
            'useEmbed' => $useEmbed,
            'flowType' => $flowType,
            'all_query_params' => $request->query()
        ]);

        // Función auxiliar para manejar redirección cuando no hay referrer
        $redirectBack = function($message, $type = 'error') use ($request) {
            try {
                if ($request->headers->get('referer')) {
                    return redirect()->back()->with($type, $message);
                } else {
                    // Si no hay referrer, redirigir a la página de cancelación
                    return redirect()->route('cancellation.form')->with($type, $message);
                }
            } catch (\Exception $e) {
                // Fallback seguro a la página de cancelación
                return redirect()->route('cancellation.form')->with($type, $message);
            }
        };

        if ($email === '') {
            \Log::warning('Email vacío en solicitud de verificación');
            return $redirectBack('El parámetro email es obligatorio.');
        }

        try {
            // Verificamos si el email existe en nuestros sistemas
            \Log::info('Buscando cliente en Baremetrics', ['email' => $email]);
            
            // Agregar timeout más corto para evitar que se quede colgado
            set_time_limit(20); // 20 segundos máximo (reducido de 30)
            
            try {
                // Configurar timeout explícito para la búsqueda de clientes
                $customers = $this->getCustomers($email);
            } catch (\Illuminate\Http\Client\ConnectionException $connectionError) {
                \Log::error('Timeout en conexión a Baremetrics', [
                    'email' => $email,
                    'error' => $connectionError->getMessage()
                ]);
                return $redirectBack('Error de conexión al buscar el cliente. Por favor, intente nuevamente.');
            } catch (\Exception $searchError) {
                \Log::error('Error en búsqueda de clientes en Baremetrics', [
                    'email' => $email,
                    'error' => $searchError->getMessage()
                ]);
                return $redirectBack('Error al buscar el cliente. Por favor, intente nuevamente.');
            }
            
            \Log::info('Resultado de búsqueda de clientes', [
                'email' => $email,
                'customers_count' => is_array($customers) ? count($customers) : 0,
                'customers_structure' => is_array($customers) && isset($customers['customers']) ? 'has_customers_key' : 'direct_array'
            ]);
            
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

            \Log::info('Clientes encontrados después del filtro', [
                'email' => $email,
                'matched_count' => count($matchedCustomers)
            ]);

            if (empty($matchedCustomers)) {
                \Log::warning('No se encontró cliente con el email', ['email' => $email]);
                return $redirectBack('No se encontró ningún cliente con ese email.');
            }

            // Verificamos si tiene suscripciones activas
            // PRIORIDAD: Usar datos de Baremetrics primero, luego verificar en Stripe si es posible
            $hasActiveSubscriptions = false;
            $maxVerificationTime = 8; // 8 segundos máximo para verificar suscripciones (reducido de 10)
            $startTime = microtime(true);
            
            // Configurar timeout explícito para Stripe API
            \Stripe\Stripe::setMaxNetworkRetries(2); // Reducir reintentos
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 5); // 5 segundos máximo para conexiones
            
            foreach ($matchedCustomers as $customer) {
                // MÉTODO 1: Verificar datos de Baremetrics primero (más confiable)
                // Si Baremetrics dice que está activo y tiene planes activos, confiamos en eso
                $isActiveInBaremetrics = (
                    (isset($customer['is_active']) && $customer['is_active'] == true) ||
                    (isset($customer['is_active']) && $customer['is_active'] == 1)
                ) && (
                    (isset($customer['is_canceled']) && $customer['is_canceled'] == false) ||
                    (isset($customer['is_canceled']) && $customer['is_canceled'] === '') ||
                    !isset($customer['is_canceled'])
                );
                
                $hasCurrentPlans = isset($customer['current_plans']) && 
                                   is_array($customer['current_plans']) && 
                                   !empty($customer['current_plans']);
                
                $hasCurrentMrr = isset($customer['current_mrr']) && 
                                $customer['current_mrr'] > 0;
                
                \Log::info('Verificando datos de Baremetrics para cliente', [
                    'email' => $email,
                    'customer_oid' => $customer['oid'] ?? 'N/A',
                    'is_active' => $isActiveInBaremetrics,
                    'has_current_plans' => $hasCurrentPlans,
                    'current_mrr' => $customer['current_mrr'] ?? 0,
                    'plans_count' => $hasCurrentPlans ? count($customer['current_plans']) : 0
                ]);
                
                // Si Baremetrics indica que está activo, confiamos en eso
                if ($isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)) {
                    \Log::info('Cliente tiene suscripciones activas según Baremetrics', [
                        'email' => $email,
                        'customer_oid' => $customer['oid'],
                        'reason' => $hasCurrentPlans ? 'tiene current_plans' : 'tiene current_mrr > 0'
                    ]);
                    $hasActiveSubscriptions = true;
                    break; // No necesitamos verificar en Stripe si Baremetrics ya lo confirma
                }
                
                // MÉTODO 2: Intentar verificar en Stripe (solo si no hay datos claros en Baremetrics)
                // Verificar timeout global del script
                if ((microtime(true) - $startTime) > $maxVerificationTime) {
                    \Log::warning('Timeout en verificación de suscripciones, usando datos de Baremetrics', [
                        'email' => $email,
                        'elapsed_time' => microtime(true) - $startTime,
                        'baremetrics_indicates_active' => $isActiveInBaremetrics
                    ]);
                    // Usar datos de Baremetrics como fallback
                    if ($isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)) {
                        $hasActiveSubscriptions = true;
                        break;
                    }
                    continue;
                }
                
                try {
                    // Intentar buscar customer en Stripe por email primero (más confiable que por OID)
                    $stripeCustomers = [];
                    try {
                        $stripeCustomersResult = \Stripe\Customer::all([
                            'email' => $email,
                            'limit' => 10
                        ]);
                        $stripeCustomers = $stripeCustomersResult->data;
                    } catch (\Exception $e) {
                        \Log::warning('No se pudo buscar customer por email en Stripe', [
                            'email' => $email,
                            'error' => $e->getMessage()
                        ]);
                    }
                    
                    // Si encontramos customers en Stripe, verificar sus suscripciones
                    if (!empty($stripeCustomers)) {
                        foreach ($stripeCustomers as $stripeCustomer) {
                            try {
                                $allSubscriptions = \Stripe\Subscription::all([
                                    'customer' => $stripeCustomer->id,
                                    'status' => 'all',
                                    'limit' => 100
                                ]);
                                
                                foreach ($allSubscriptions->data as $subscription) {
                                    if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                                        $hasActiveSubscriptions = true;
                                        \Log::info('Suscripción activa encontrada en Stripe (buscada por email)', [
                                            'email' => $email,
                                            'stripe_customer_id' => $stripeCustomer->id,
                                            'subscription_id' => $subscription->id,
                                            'status' => $subscription->status
                                        ]);
                                        break 2; // Salir de ambos loops
                                    }
                                }
                            } catch (\Exception $subError) {
                                \Log::warning('Error verificando suscripciones en Stripe por customer ID', [
                                    'stripe_customer_id' => $stripeCustomer->id ?? 'N/A',
                                    'error' => $subError->getMessage()
                                ]);
                            }
                        }
                        
                        // Si ya encontramos suscripciones activas, no continuar con el método del OID
                        if ($hasActiveSubscriptions) {
                            break;
                        }
                    }
                    
                    // Intentar también con el OID de Baremetrics (puede fallar pero lo intentamos)
                    try {
                        $allSubscriptions = \Stripe\Subscription::all([
                            'customer' => $customer['oid'],
                            'status' => 'all',
                            'limit' => 100
                        ]);
                        
                        foreach ($allSubscriptions->data as $subscription) {
                            if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                                $hasActiveSubscriptions = true;
                                \Log::info('Suscripción activa encontrada en Stripe (buscada por OID)', [
                                    'customer_oid' => $customer['oid'],
                                    'subscription_id' => $subscription->id,
                                    'status' => $subscription->status
                                ]);
                                break 2;
                            }
                        }
                    } catch (\Stripe\Exception\InvalidRequestException $oidError) {
                        // El OID no es válido en Stripe, esto es normal y lo ignoramos
                        \Log::debug('OID de Baremetrics no válido en Stripe (normal)', [
                            'customer_oid' => $customer['oid'],
                            'email' => $email
                        ]);
                    }
                    
                } catch (\Stripe\Exception\ApiConnectionException $e) {
                    // Error de conexión/timeout de Stripe - usar datos de Baremetrics
                    \Log::warning('Error de conexión con Stripe, usando datos de Baremetrics', [
                        'customer_id' => $customer['oid'],
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'elapsed_time' => microtime(true) - $startTime,
                        'baremetrics_active' => $isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)
                    ]);
                    
                    // Si Baremetrics indica activo, confiamos en eso
                    if ($isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)) {
                        $hasActiveSubscriptions = true;
                        break;
                    }
                    continue;
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Customer no encontrado en Stripe - usar datos de Baremetrics
                    \Log::warning('Customer no encontrado en Stripe, usando datos de Baremetrics', [
                        'customer_oid' => $customer['oid'],
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'baremetrics_active' => $isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)
                    ]);
                    
                    // Confiar en los datos de Baremetrics si indican que está activo
                    if ($isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)) {
                        \Log::info('Usando datos de Baremetrics: cliente activo con suscripciones', [
                            'email' => $email,
                            'customer_oid' => $customer['oid']
                        ]);
                        $hasActiveSubscriptions = true;
                        break;
                    }
                } catch (\Exception $e) {
                    // Otro tipo de error de Stripe - usar datos de Baremetrics
                    \Log::error('Error inesperado al obtener suscripciones de Stripe, usando datos de Baremetrics', [
                        'customer_id' => $customer['oid'],
                        'email' => $email,
                        'error_type' => get_class($e),
                        'error' => $e->getMessage(),
                        'baremetrics_active' => $isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)
                    ]);
                    
                    // Confiar en Baremetrics si indica activo
                    if ($isActiveInBaremetrics && ($hasCurrentPlans || $hasCurrentMrr)) {
                        $hasActiveSubscriptions = true;
                        break;
                    }
                    continue;
                }
            }
            
            // Restaurar configuración original de timeout
            ini_set('default_socket_timeout', $originalTimeout);
            
            \Log::info('Verificación de suscripciones activas completada', [
                'email' => $email,
                'hasActiveSubscriptions' => $hasActiveSubscriptions
            ]);

            if (!$hasActiveSubscriptions) {
                return $redirectBack('No tienes membresías activas de Stripe que puedas cancelar. Todas tus membresías ya se encuentran canceladas.');
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
            
            // Generamos la URL de verificación según el tipo de flujo
            if ($flowType === 'embed') {
                $verificationUrl = route('cancellation.verify.embed', ['token' => $token]);
            } else {
                $verificationUrl = route('cancellation.verify', ['token' => $token]);
            }
            
            \Log::info('URL de verificación generada', [
                'email' => $email,
                'flowType' => $flowType,
                'verification_url' => $verificationUrl,
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            
            // Enviamos el correo con el enlace de verificación (con timeout para evitar bloqueos)
            \Log::info('Intentando enviar correo de verificación', [
                'email' => $email,
                'flowType' => $flowType
            ]);
            
            // Configurar timeout para el envío de correos
            $mailTimeout = 8; // 8 segundos máximo para enviar correos
            $mailStartTime = microtime(true);
            
            try {
                $mailSent = $this->sendVerificationEmail($email, $verificationUrl, $flowType);
                
                // Verificar si el envío tardó mucho
                $mailElapsedTime = microtime(true) - $mailStartTime;
                if ($mailElapsedTime > $mailTimeout) {
                    \Log::warning('El envío de correo tardó más del esperado', [
                        'email' => $email,
                        'elapsed_time' => $mailElapsedTime
                    ]);
                }
            } catch (\Exception $mailError) {
                \Log::error('Excepción al enviar correo', [
                    'email' => $email,
                    'error' => $mailError->getMessage()
                ]);
                $mailSent = false;
            }
            
            \Log::info('Resultado del envío de correo', [
                'email' => $email,
                'mailSent' => $mailSent,
                'flowType' => $flowType,
                'elapsed_time' => microtime(true) - $mailStartTime
            ]);
            
            if ($mailSent) {
                // Rastrear que se solicitó el correo de cancelación
                try {
                    $tracking = CancellationTracking::getOrCreateByEmail($email, $token);
                    $tracking->markEmailRequested($token);
                    \Log::info('Seguimiento de cancelación: correo solicitado', [
                        'email' => $email,
                        'tracking_id' => $tracking->id
                    ]);
                    
                    // Enviar correo de resumen a administradores
                    $this->sendCancellationSummaryEmail($tracking, 'email_requested');
                } catch (\Exception $trackingError) {
                    \Log::error('Error al rastrear solicitud de correo de cancelación', [
                        'email' => $email,
                        'error' => $trackingError->getMessage()
                    ]);
                }

                \Log::info('Correo enviado exitosamente, mostrando vista de confirmación', ['email' => $email]);
                return view('cancellation.verification-sent', [
                    'email' => $email
                ])->with('success', 'Se ha enviado un enlace de verificación a su correo electrónico. El enlace expirará en 30 minutos.');
            } else {
                \Log::error('Fallo en envío de correo', ['email' => $email, 'flowType' => $flowType]);
                return view('cancellation.verification-sent', [
                    'email' => $email
                ])->with('error', 'No se pudo enviar el correo de verificación. Por favor, intente nuevamente.');
            }
            
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de verificación: ' . $e->getMessage(), [
                'email' => $email,
                'flowType' => $flowType,
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

        // Rastrear que el usuario vio la encuesta
        try {
            $tracking = CancellationTracking::where('token', $token)
                ->orWhere('email', $email)
                ->latest()
                ->first();
            
            if ($tracking) {
                $tracking->markSurveyViewed($customerId);
                \Log::info('Seguimiento de cancelación: encuesta vista', [
                    'email' => $email,
                    'customer_id' => $customerId,
                    'tracking_id' => $tracking->id
                ]);
                
                // Enviar correo de resumen a administradores
                $this->sendCancellationSummaryEmail($tracking, 'survey_viewed');
            }
        } catch (\Exception $trackingError) {
            \Log::error('Error al rastrear visualización de encuesta', [
                'email' => $email,
                'error' => $trackingError->getMessage()
            ]);
        }

        // Redirigir directamente a la survey con los datos del cliente
        return redirect()->route('cancellation.survey', ['customer_id' => $customerId]);
    }

    /**
     * Verifica el token mágico y redirige al proceso de cancelación con embed
     */
    public function verifyCancellationTokenEmbed(Request $request)
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

        // Obtener suscripciones activas
        // PRIORIDAD: Usar datos de Baremetrics primero, luego verificar en Stripe si es posible
        $activeSubscriptions = [];
        
        // MÉTODO 1: Verificar datos de Baremetrics primero
        $isActiveInBaremetrics = (
            (isset($customer['is_active']) && $customer['is_active'] == true) ||
            (isset($customer['is_active']) && $customer['is_active'] == 1)
        ) && (
            (isset($customer['is_canceled']) && $customer['is_canceled'] == false) ||
            (isset($customer['is_canceled']) && $customer['is_canceled'] === '') ||
            !isset($customer['is_canceled'])
        );
        
        $hasCurrentPlans = isset($customer['current_plans']) && 
                          is_array($customer['current_plans']) && 
                          !empty($customer['current_plans']);
        
        $hasCurrentMrr = isset($customer['current_mrr']) && 
                        $customer['current_mrr'] > 0;
        
        \Log::info('verifyCancellationTokenEmbed - Verificando datos de Baremetrics', [
            'email' => $email,
            'customer_oid' => $customerId,
            'is_active' => $isActiveInBaremetrics,
            'has_current_plans' => $hasCurrentPlans,
            'current_mrr' => $customer['current_mrr'] ?? 0,
            'plans_count' => $hasCurrentPlans ? count($customer['current_plans']) : 0
        ]);
        
        // Si Baremetrics indica que está activo, obtener las suscripciones reales desde Baremetrics
        if ($isActiveInBaremetrics && $hasCurrentPlans) {
            \Log::info('Obteniendo suscripciones reales de Baremetrics', [
                'email' => $email,
                'customer_oid' => $customerId,
                'source_id' => $customer['source_id'] ?? null,
                'plans_count' => count($customer['current_plans'])
            ]);
            
            // Obtener source_id del customer
            $sourceId = $customer['source_id'] ?? null;
            
            if ($sourceId) {
                try {
                    // Obtener todas las suscripciones del source
                    $allBaremetricsSubscriptions = $this->baremetricsService->getSubscriptions($sourceId);
                    
                    if ($allBaremetricsSubscriptions && isset($allBaremetricsSubscriptions['subscriptions'])) {
                        // Buscar suscripciones del cliente que coincidan con los planes activos
                        foreach ($allBaremetricsSubscriptions['subscriptions'] as $bmSubscription) {
                            $subscriptionCustomerOid = $bmSubscription['customer_oid'] ?? 
                                                     $bmSubscription['customer']['oid'] ?? 
                                                     $bmSubscription['customerOid'] ?? 
                                                     null;
                            
                            // Verificar que la suscripción pertenece al cliente
                            if ($subscriptionCustomerOid === $customerId) {
                                $subscriptionPlanOid = $bmSubscription['plan_oid'] ?? 
                                                      $bmSubscription['plan']['oid'] ?? 
                                                      null;
                                
                                // Verificar que el plan coincide con alguno de los current_plans
                                $matchesPlan = false;
                                $matchedPlan = null;
                                foreach ($customer['current_plans'] as $plan) {
                                    if (($plan['oid'] ?? null) === $subscriptionPlanOid) {
                                        $matchesPlan = true;
                                        $matchedPlan = $plan;
                                        break;
                                    }
                                }
                                
                                // Solo agregar suscripciones activas que coincidan con current_plans
                                if ($matchesPlan && ($bmSubscription['active'] ?? false)) {
                                    $planData = [
                                        'oid' => $subscriptionPlanOid ?? $matchedPlan['oid'],
                                        'name' => $matchedPlan['name'] ?? $bmSubscription['plan']['name'] ?? 'Plan',
                                        'amount' => isset($matchedPlan['amounts'][0]['amount']) ? $matchedPlan['amounts'][0]['amount'] / 100 : 0,
                                        'currency' => strtoupper($matchedPlan['amounts'][0]['currency'] ?? 'USD'),
                                        'interval' => $matchedPlan['interval'] ?? 'month',
                                        'interval_count' => $matchedPlan['interval_count'] ?? 1
                                    ];
                                    
                                    // Usar el subscription_oid real de Baremetrics (NO el plan_oid!)
                                    $subscriptionOid = $bmSubscription['oid'] ?? null;
                                    
                                    if ($subscriptionOid) {
                                        $activeSubscriptions[] = [
                                            'subscription' => (object)[
                                                'id' => $subscriptionOid, // Usar subscription OID real
                                                'customer' => $customerId,
                                                'status' => 'active'
                                            ],
                                            'plan' => $planData,
                                            'customer_id' => $customerId,
                                            'subscription_id' => $subscriptionOid, // Usar subscription OID real
                                            'baremetrics_subscription_oid' => $subscriptionOid, // OID real de Baremetrics
                                            'baremetrics_plan_oid' => $subscriptionPlanOid, // OID del plan/precio
                                            'from_baremetrics' => true
                                        ];
                                        
                                        \Log::info('Suscripción activa encontrada en Baremetrics', [
                                            'email' => $email,
                                            'customer_oid' => $customerId,
                                            'subscription_oid' => $subscriptionOid,
                                            'plan_oid' => $subscriptionPlanOid,
                                            'plan_name' => $planData['name']
                                        ]);
                                    }
                                }
                            }
                        }
                    } else {
                        \Log::warning('No se pudieron obtener suscripciones de Baremetrics', [
                            'source_id' => $sourceId,
                            'customer_oid' => $customerId
                        ]);
                    }
                } catch (\Exception $bmError) {
                    \Log::error('Error obteniendo suscripciones de Baremetrics', [
                        'customer_oid' => $customerId,
                        'source_id' => $sourceId,
                        'error' => $bmError->getMessage()
                    ]);
                }
            }
            
            // Si aún no encontramos suscripciones, usar fallback con current_plans
            if (empty($activeSubscriptions)) {
                \Log::warning('No se encontraron suscripciones en Baremetrics API, usando fallback con current_plans', [
                    'email' => $email,
                    'customer_oid' => $customerId
                ]);
                
                foreach ($customer['current_plans'] as $plan) {
                    $planData = [
                        'oid' => $plan['oid'] ?? $customerId,
                        'name' => $plan['name'] ?? 'Plan',
                        'amount' => isset($plan['amounts'][0]['amount']) ? $plan['amounts'][0]['amount'] / 100 : 0,
                        'currency' => strtoupper($plan['amounts'][0]['currency'] ?? 'USD'),
                        'interval' => $plan['interval'] ?? 'month',
                        'interval_count' => $plan['interval_count'] ?? 1
                    ];
                    
                    // En este caso usamos el plan_oid como fallback, pero debería ser temporáneo
                    $activeSubscriptions[] = [
                        'subscription' => (object)[
                            'id' => $plan['oid'],
                            'customer' => $customerId,
                            'status' => 'active'
                        ],
                        'plan' => $planData,
                        'customer_id' => $customerId,
                        'subscription_id' => $plan['oid'],
                        'baremetrics_plan_oid' => $plan['oid'],
                        'from_baremetrics' => true,
                        'is_fallback' => true // Marca que es un fallback
                    ];
                }
            }
        }
        
        // MÉTODO 2: Intentar obtener suscripciones de Stripe (solo si no las tenemos de Baremetrics)
        if (empty($activeSubscriptions)) {
            \Log::info('No se encontraron suscripciones en Baremetrics, intentando Stripe', [
                'email' => $email,
                'customer_oid' => $customerId
            ]);
            
            try {
                // Primero intentar por email
                $stripeCustomers = [];
                try {
                    $stripeCustomersResult = \Stripe\Customer::all([
                        'email' => $email,
                        'limit' => 10
                    ]);
                    $stripeCustomers = $stripeCustomersResult->data;
                } catch (\Exception $e) {
                    \Log::debug('No se pudo buscar customer por email en Stripe', [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Buscar suscripciones usando los customers encontrados por email
                if (!empty($stripeCustomers)) {
                    foreach ($stripeCustomers as $stripeCustomer) {
                        try {
                            $allSubscriptions = \Stripe\Subscription::all([
                                'customer' => $stripeCustomer->id,
                                'status' => 'all',
                                'limit' => 100
                            ]);
                            
                            foreach ($allSubscriptions->data as $subscription) {
                                if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                                    $plan = [
                                        'oid' => $subscription->plan->id,
                                        'name' => $subscription->plan->nickname ?? 'Plan ' . ($subscription->plan->amount/100) . ' ' . strtoupper($subscription->plan->currency ?? 'USD'),
                                        'amount' => $subscription->plan->amount/100,
                                        'currency' => strtoupper($subscription->plan->currency ?? 'USD'),
                                        'interval' => $subscription->plan->interval ?? 'month',
                                        'interval_count' => $subscription->plan->interval_count ?? 1
                                    ];
                                    
                                    $activeSubscriptions[] = [
                                        'subscription' => $subscription,
                                        'plan' => $plan,
                                        'customer_id' => $customerId,
                                        'subscription_id' => $subscription->id,
                                        'from_baremetrics' => false
                                    ];
                                }
                            }
                        } catch (\Exception $subError) {
                            \Log::warning('Error verificando suscripciones en Stripe por customer ID', [
                                'stripe_customer_id' => $stripeCustomer->id ?? 'N/A',
                                'error' => $subError->getMessage()
                            ]);
                        }
                    }
                }
                
                // También intentar con el OID de Baremetrics (puede fallar pero lo intentamos)
                if (empty($activeSubscriptions)) {
                    try {
                        $allSubscriptions = \Stripe\Subscription::all([
                            'customer' => $customerId,
                            'status' => 'all',
                            'limit' => 100
                        ]);
                        
                        foreach ($allSubscriptions->data as $subscription) {
                            if ($subscription->status === 'active' || $subscription->status === 'trialing') {
                                $plan = [
                                    'oid' => $subscription->plan->id,
                                    'name' => $subscription->plan->nickname ?? 'Plan ' . ($subscription->plan->amount/100) . ' ' . strtoupper($subscription->plan->currency ?? 'USD'),
                                    'amount' => $subscription->plan->amount/100,
                                    'currency' => strtoupper($subscription->plan->currency ?? 'USD'),
                                    'interval' => $subscription->plan->interval ?? 'month',
                                    'interval_count' => $subscription->plan->interval_count ?? 1
                                ];
                                
                                $activeSubscriptions[] = [
                                    'subscription' => $subscription,
                                    'plan' => $plan,
                                    'customer_id' => $customerId,
                                    'subscription_id' => $subscription->id,
                                    'from_baremetrics' => false
                                ];
                            }
                        }
                    } catch (\Stripe\Exception\InvalidRequestException $oidError) {
                        // El OID no es válido en Stripe, esto es normal
                        \Log::debug('OID de Baremetrics no válido en Stripe (normal)', [
                            'customer_oid' => $customerId,
                            'email' => $email
                        ]);
                    }
                }
                
            } catch (\Exception $e) {
                \Log::error('Error al obtener suscripciones de Stripe en verifyCancellationTokenEmbed: ' . $e->getMessage(), [
                    'customer_id' => $customerId,
                    'email' => $email,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Si aún no hay suscripciones activas pero Baremetrics indica que está activo, usar fallback
        if (empty($activeSubscriptions) && $isActiveInBaremetrics && $hasCurrentMrr) {
            \Log::warning('No se encontraron suscripciones específicas pero Baremetrics indica activo (MRR > 0), usando fallback', [
                'email' => $email,
                'customer_oid' => $customerId,
                'current_mrr' => $customer['current_mrr']
            ]);
            
            // Crear una suscripción genérica basada en el MRR
            $activeSubscriptions[] = [
                'subscription' => (object)[
                    'id' => $customerId . '_generic',
                    'customer' => $customerId,
                    'status' => 'active'
                ],
                'plan' => [
                    'oid' => $customerId,
                    'name' => 'Suscripción Activa',
                    'amount' => $customer['current_mrr'] / 100,
                    'currency' => 'USD',
                    'interval' => 'month',
                    'interval_count' => 1
                ],
                'customer_id' => $customerId,
                'subscription_id' => $customerId . '_generic',
                'from_baremetrics' => true
            ];
        }

        \Log::info('verifyCancellationTokenEmbed - Suscripciones activas encontradas', [
            'email' => $email,
            'customer_oid' => $customerId,
            'active_subscriptions_count' => count($activeSubscriptions),
            'from_baremetrics' => array_filter($activeSubscriptions, fn($s) => $s['from_baremetrics'] ?? false)
        ]);

        if (empty($activeSubscriptions)) {
            return redirect()->route('cancellation.form')->with('error', 'No tienes membresías activas de Stripe que puedas cancelar. Todas tus membresías ya se encuentran canceladas.');
        }

        // Almacenar información del cliente en la sesión
        session([
            'cancellation_customer' => $customer,
            'cancellation_customer_id' => $customerId,
            'cancellation_email' => $email,
            'cancellation_active_subscriptions' => $activeSubscriptions
        ]);

        // Rastrear que el usuario vio la encuesta (embed)
        try {
            $tracking = CancellationTracking::where('token', $token)
                ->orWhere('email', $email)
                ->latest()
                ->first();
            
            if ($tracking) {
                $tracking->markSurveyViewed($customerId);
                \Log::info('Seguimiento de cancelación: encuesta vista (embed)', [
                    'email' => $email,
                    'customer_id' => $customerId,
                    'tracking_id' => $tracking->id
                ]);
                
                // Enviar correo de resumen a administradores
                $this->sendCancellationSummaryEmail($tracking, 'survey_viewed');
            }
        } catch (\Exception $trackingError) {
            \Log::error('Error al rastrear visualización de encuesta (embed)', [
                'email' => $email,
                'error' => $trackingError->getMessage()
            ]);
        }

        // Si hay solo una suscripción activa, redirigimos directamente al embed
        if (count($activeSubscriptions) === 1) {
            $subscription = $activeSubscriptions[0];
            return redirect()->route('cancellation.embed', [
                'customer_id' => $subscription['customer_id'],
                'subscription_id' => $subscription['subscription_id']
            ]);
        }
        
        // Si hay múltiples suscripciones, mostramos una vista para seleccionar cuál cancelar
        return view('cancellation.select_subscription_embed', [
            'email' => $email,
            'customer' => $customer,
            'activeSubscriptions' => $activeSubscriptions
        ]);
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
        // Intentar obtener desde config primero (recomendado en Laravel), luego desde env
        $emailsString = config('mail.cancellation_notification_emails', '');
        
        if (empty($emailsString)) {
            // Fallback a env() si no está en config
            $emailsString = env('CANCELLATION_NOTIFICATION_EMAILS', '');
        }
        
        \Log::info('Leyendo CANCELLATION_NOTIFICATION_EMAILS', [
            'emails_string' => $emailsString,
            'from_config' => !empty(config('mail.cancellation_notification_emails', '')),
            'from_env' => !empty(env('CANCELLATION_NOTIFICATION_EMAILS', ''))
        ]);
        
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
            'emails_raw' => $emails,
            'emails_valid' => $validEmails,
            'total' => count($validEmails)
        ]);
        
        return array_values($validEmails);
    }

    /**
     * Envía un correo de resumen del proceso de cancelación a los administradores
     * 
     * @param CancellationTracking $tracking El registro de seguimiento
     * @param string $triggerEvent El evento que desencadenó el envío del correo
     */
    private function sendCancellationSummaryEmail(CancellationTracking $tracking, string $triggerEvent = 'status_update')
    {
        try {
            $adminEmails = $this->getCancellationNotificationEmails();
            
            if (empty($adminEmails)) {
                \Log::warning('No hay correos de administrador configurados para enviar resumen de cancelación', [
                    'tracking_id' => $tracking->id,
                    'email' => $tracking->email
                ]);
                return false;
            }

            $status = $tracking->getCurrentStatus();
            
            \Log::info('Enviando correo de resumen de cancelación a administradores', [
                'tracking_id' => $tracking->id,
                'email' => $tracking->email,
                'status' => $status,
                'trigger_event' => $triggerEvent,
                'admin_emails_count' => count($adminEmails)
            ]);

            $adminEmailsSent = [];
            $adminEmailsFailed = [];

            foreach ($adminEmails as $adminEmail) {
                try {
                    Mail::send('emails.cancellation-summary', [
                        'tracking' => $tracking,
                        'status' => $status,
                        'triggerEvent' => $triggerEvent
                    ], function($message) use ($tracking, $adminEmail, $status) {
                        $statusText = $this->getStatusText($status);
                        $subject = "Resumen de Cancelación - {$tracking->email} - {$statusText}";
                        $message->to($adminEmail)
                            ->subject($subject);
                    });

                    $adminEmailsSent[] = $adminEmail;
                    \Log::info('Correo de resumen enviado exitosamente a administrador', [
                        'admin_email' => $adminEmail,
                        'user_email' => $tracking->email,
                        'tracking_id' => $tracking->id
                    ]);
                } catch (\Exception $mailError) {
                    $adminEmailsFailed[] = $adminEmail;
                    \Log::error('Error al enviar correo de resumen a administrador', [
                        'admin_email' => $adminEmail,
                        'user_email' => $tracking->email,
                        'error' => $mailError->getMessage()
                    ]);
                }
            }

            \Log::info('Correos de resumen procesados', [
                'total' => count($adminEmails),
                'sent' => count($adminEmailsSent),
                'failed' => count($adminEmailsFailed),
                'tracking_id' => $tracking->id
            ]);

            return count($adminEmailsSent) > 0;
        } catch (\Exception $e) {
            \Log::error('Error al enviar correo de resumen de cancelación', [
                'tracking_id' => $tracking->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Obtiene el texto descriptivo del estado
     */
    private function getStatusText(string $status): string
    {
        $statusMap = [
            'not_started' => 'No Iniciado',
            'email_requested' => 'Correo Solicitado',
            'survey_viewed' => 'Encuesta Vista',
            'survey_completed' => 'Encuesta Completada',
            'baremetrics_cancelled' => 'Cancelado en Baremetrics',
            'stripe_cancelled' => 'Cancelado en Stripe',
            'cancelled_both' => 'Cancelaciones Completadas',
            'completed' => 'Proceso Completo'
        ];

        return $statusMap[$status] ?? 'Estado Desconocido';
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

    /**
     * Procesar cancelación de la suscripción con datos del embed de Baremetrics
     */
    public function cancelSubscriptionWithEmbed(Request $request)
    {
        $customer_id = $request->get('customer_id');
        $subscription_id = $request->get('subscription_id');
        $baremetrics_subscription_oid = $request->get('baremetrics_subscription_oid');
        $cancellation_reason = $request->get('cancellation_reason', '');
        $cancellation_comments = $request->get('cancellation_comments', '');
        $barecancel_data = $request->get('barecancel_data', '');
        $sync_only = $request->get('sync_only', false); // Si es true, solo sincronización (Baremetrics ya canceló)

        \Log::info('Procesando con embed de Baremetrics', [
            'customer_id' => $customer_id,
            'subscription_id' => $subscription_id,
            'baremetrics_subscription_oid' => $baremetrics_subscription_oid,
            'sync_only' => $sync_only,
            'has_reason' => !empty($cancellation_reason),
            'has_comments' => !empty($cancellation_comments),
            'note' => $sync_only ? 'Sincronización y cancelación en Stripe' : 'Cancelación manual (legacy)'
        ]);

        try {
            // Obtener datos de la sesión
            $email = session('cancellation_email');
            $customer = session('cancellation_customer');
            $tracking = null;
            $stripeCustomerId = null;

            if (!empty($email)) {
                try {
                    $tracking = CancellationTracking::where('email', $email)
                        ->latest()
                        ->first();

                    if (!$tracking) {
                        $tracking = CancellationTracking::create([
                            'email' => $email,
                            'customer_id' => $customer_id,
                        ]);

                        \Log::info('Seguimiento de cancelación: registro creado desde embed', [
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'tracking_id' => $tracking->id
                        ]);
                    } elseif ($customer_id && empty($tracking->customer_id)) {
                        $tracking->update(['customer_id' => $customer_id]);
                    }

                    $stripeCustomerId = $tracking->stripe_customer_id;

                    if (!$stripeCustomerId) {
                        $stripeCustomerId = $this->getStripeCustomerId($customer_id, $email);
                    }

                    $wasSurveyCompleted = (bool) $tracking->survey_completed;

                    $tracking->markSurveyCompleted($customer_id, $stripeCustomerId);

                    \Log::info('Seguimiento de cancelación: encuesta completada (embed)', [
                        'email' => $email,
                        'customer_id' => $customer_id,
                        'tracking_id' => $tracking->id
                    ]);

                    $tracking = $tracking->fresh();
                    if ($tracking && !$stripeCustomerId && $tracking->stripe_customer_id) {
                        $stripeCustomerId = $tracking->stripe_customer_id;
                    }

                    if ($tracking && !$wasSurveyCompleted) {
                        $this->sendCancellationSummaryEmail($tracking, 'survey_completed');
                    }
                } catch (\Exception $trackingError) {
                    \Log::error('Error al registrar completación de encuesta (embed)', [
                        'email' => $email,
                        'customer_id' => $customer_id,
                        'error' => $trackingError->getMessage()
                    ]);
                }
            }

            // IMPORTANTE: Si sync_only es true, Baremetrics canceló en su sistema
            // Pero necesitamos cancelar también en Stripe
            if ($sync_only) {
                \Log::info('Sincronización después de cancelación de Baremetrics - Cancelando en Stripe', [
                    'customer_id' => $customer_id,
                    'email' => $email,
                    'subscription_id' => $subscription_id
                ]);
                
                // Guardar el motivo de cancelación en nuestra BD para registro
                if ($email && $cancellation_reason) {
                    try {
                        // Obtener el ID de Stripe del cliente
                        if (!$stripeCustomerId) {
                            $stripeCustomerId = $this->getStripeCustomerId($customer_id, $email);
                        }
                        
                        CancellationSurvey::create([
                            'customer_id' => $customer_id,
                            'stripe_customer_id' => $stripeCustomerId,
                            'email' => $email,
                            'reason' => $cancellation_reason,
                            'additional_comments' => $cancellation_comments,
                        ]);
                        
                        \Log::info('Survey de cancelación guardado (sincronización post-Baremetrics)', [
                            'customer_id' => $customer_id,
                            'email' => $email,
                            'reason' => $cancellation_reason
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Error guardando survey (sincronización): ' . $e->getMessage());
                        // No es crítico, continuar
                    }
                }
                
                // Actualizar custom fields en Baremetrics con el motivo y comentarios de cancelación
                if ($cancellation_reason) {
                    try {
                        $baremetricsData = [
                            'cancellation_reason' => $cancellation_reason,
                        ];
                        
                        if (!empty($cancellation_comments)) {
                            $baremetricsData['cancellation_comments'] = $cancellation_comments;
                        }
                        
                        $updateResult = $this->baremetricsService->updateCustomerAttributes($customer_id, $baremetricsData);
                        
                        if ($updateResult) {
                            \Log::info('Custom fields de Baremetrics actualizados desde embed (sincronización)', [
                                'customer_id' => $customer_id,
                                'updated_fields' => array_keys($baremetricsData)
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error actualizando custom fields en Baremetrics desde embed (sincronización): ' . $e->getMessage());
                    }
                }
                
                // Registrar el motivo de cancelación en Barecancel Insights
                if ($cancellation_reason) {
                    try {
                        $barecancelResult = $this->baremetricsService->recordCancellationReason(
                            $customer_id, 
                            $cancellation_reason, 
                            $cancellation_comments
                        );
                        
                        if ($barecancelResult) {
                            \Log::info('Motivo de cancelación registrado en Barecancel desde embed (sincronización)', [
                                'customer_id' => $customer_id,
                                'reason' => $cancellation_reason
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error registrando motivo en Barecancel desde embed (sincronización): ' . $e->getMessage());
                    }
                }
                
                // Actualizar GoHighLevel con el motivo de cancelación
                if ($email && $cancellation_reason) {
                    try {
                        $ghlService = app(\App\Services\GoHighLevelService::class);
                        $ghlContact = $ghlService->getContactsByExactEmail($email);
                        
                        if (empty($ghlContact['contacts'])) {
                            $ghlContact = $ghlService->getContacts($email);
                        }
                        
                        if (!empty($ghlContact['contacts'])) {
                            $contactId = $ghlContact['contacts'][0]['id'];
                            
                            $customFields = [
                                'UhyA0ol6XoETLRA5jsZa' => $cancellation_reason,
                            ];
                            
                            if (!empty($cancellation_comments)) {
                                $customFields['zYi50QSDZC6eGqoRH8Zm'] = $cancellation_comments;
                            }
                            
                            $ghlService->updateContactCustomFields($contactId, $customFields);
                            \Log::info('Motivo de cancelación actualizado en GHL (sincronización)', [
                                'customer_id' => $customer_id,
                                'email' => $email
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error actualizando motivo en GHL (sincronización): ' . $e->getMessage());
                        // No es crítico, continuar
                    }
                }

                if ($tracking && !$tracking->baremetrics_cancelled) {
                    try {
                        $baremetricsDetails = [
                            'baremetrics_subscription_oid' => $baremetrics_subscription_oid,
                            'subscription_id' => $subscription_id,
                            'source' => 'embed_sync'
                        ];

                        if (!empty($barecancel_data)) {
                            $decodedBarecancel = json_decode($barecancel_data, true);
                            $baremetricsDetails['barecancel'] = $decodedBarecancel ?: $barecancel_data;
                        }

                        $tracking->markBaremetricsCancelled(json_encode(
                            $baremetricsDetails,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ));

                        \Log::info('Seguimiento de cancelación: cancelado en Baremetrics (embed)', [
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'subscription_id' => $subscription_id,
                            'tracking_id' => $tracking->id
                        ]);

                        $tracking = $tracking->fresh();
                        if ($tracking) {
                            $this->sendCancellationSummaryEmail($tracking, 'baremetrics_cancelled');
                        }
                    } catch (\Exception $trackingError) {
                        \Log::error('Error al rastrear cancelación en Baremetrics (embed)', [
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'error' => $trackingError->getMessage()
                        ]);
                    }
                }
                
                // IMPORTANTE: Cancelar la suscripción en Stripe
                // El subscription_id puede ser el ID de Stripe o el OID de Baremetrics
                $stripeSubscriptionId = null;
                
                // Intentar obtener el ID de Stripe de la suscripción
                // Primero verificar si subscription_id es un ID de Stripe
                if (!empty($subscription_id) && strpos($subscription_id, 'sub_') === 0) {
                    $stripeSubscriptionId = $subscription_id;
                    \Log::info('Subscription ID es de Stripe', [
                        'subscription_id' => $subscription_id
                    ]);
                } else {
                    // No es un ID de Stripe, buscar la suscripción en Stripe usando el customer_id
                    \Log::info('Buscando suscripción activa en Stripe', [
                        'subscription_id' => $subscription_id,
                        'baremetrics_subscription_oid' => $baremetrics_subscription_oid,
                        'customer_id' => $customer_id
                    ]);
                    
                    try {
                        // Buscar todas las suscripciones activas del cliente en Stripe
                        $allSubscriptions = \Stripe\Subscription::all([
                            'customer' => $customer_id,
                            'status' => 'all',
                            'limit' => 100
                        ]);
                        
                        // Buscar la suscripción activa (puede haber múltiples)
                        foreach ($allSubscriptions->data as $stripeSubscription) {
                            if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                                $stripeSubscriptionId = $stripeSubscription->id;
                                \Log::info('Suscripción activa encontrada en Stripe', [
                                    'stripe_subscription_id' => $stripeSubscriptionId,
                                    'status' => $stripeSubscription->status
                                ]);
                                break; // Usar la primera suscripción activa encontrada
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error buscando suscripción en Stripe: ' . $e->getMessage(), [
                            'customer_id' => $customer_id,
                            'subscription_id' => $subscription_id,
                            'baremetrics_subscription_oid' => $baremetrics_subscription_oid
                        ]);
                    }
                }
                
                // Cancelar en Stripe si encontramos la suscripción
                if ($stripeSubscriptionId) {
                    try {
                        $stripeResult = $this->stripeService->cancelActiveSubscription($customer_id, $stripeSubscriptionId);
                        
                        if ($stripeResult['success']) {
                            // Rastrear cancelación en Stripe (embed)
                            try {
                                if ($email) {
                                    $tracking = CancellationTracking::where('email', $email)
                                        ->latest()
                                        ->first();
                                    
                                    if ($tracking) {
                                        $details = json_encode([
                                            'subscription_id' => $stripeSubscriptionId,
                                            'details' => $stripeResult['data'] ?? null,
                                            'source' => 'embed'
                                        ]);
                                        $tracking->markStripeCancelled($details);
                                        \Log::info('Seguimiento de cancelación: cancelado en Stripe (embed)', [
                                            'email' => $email,
                                            'customer_id' => $customer_id,
                                            'subscription_id' => $stripeSubscriptionId,
                                            'tracking_id' => $tracking->id
                                        ]);
                                        
                                        // Enviar correo de resumen a administradores
                                        $this->sendCancellationSummaryEmail($tracking, 'stripe_cancelled');
                                        
                                        // Verificar si el proceso está completo y enviar correo final
                                        $tracking->refresh(); // Recargar para obtener el estado actualizado
                                        if ($tracking->process_completed) {
                                            $this->sendCancellationSummaryEmail($tracking, 'process_completed');
                                        }
                                    }
                                }
                            } catch (\Exception $trackingError) {
                                \Log::error('Error al rastrear cancelación en Stripe (embed)', [
                                    'email' => $email,
                                    'error' => $trackingError->getMessage()
                                ]);
                            }
                            
                            \Log::info('Suscripción cancelada exitosamente en Stripe después del embed', [
                                'customer_id' => $customer_id,
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'cancellation_details' => $stripeResult['data'] ?? null
                            ]);
                        } else {
                            \Log::error('Error cancelando suscripción en Stripe después del embed', [
                                'customer_id' => $customer_id,
                                'stripe_subscription_id' => $stripeSubscriptionId,
                                'error' => $stripeResult['error'] ?? 'Error desconocido'
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::error('Excepción al cancelar suscripción en Stripe después del embed: ' . $e->getMessage(), [
                            'customer_id' => $customer_id,
                            'stripe_subscription_id' => $stripeSubscriptionId,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    \Log::warning('No se pudo determinar el ID de suscripción de Stripe para cancelar', [
                        'customer_id' => $customer_id,
                        'subscription_id' => $subscription_id
                    ]);
                }
                
                // Retornar éxito
                return response()->json([
                    'success' => true,
                    'message' => 'Datos sincronizados y suscripción cancelada en Stripe correctamente.'
                ]);
            }

            // CÓDIGO LEGACY: Solo para compatibilidad si alguien llama sin sync_only
            // Esto NO debería ejecutarse normalmente porque Baremetrics maneja la cancelación
            \Log::warning('cancelSubscriptionWithEmbed llamado sin sync_only - esto no debería pasar', [
                'customer_id' => $customer_id,
                'subscription_id' => $subscription_id
            ]);

            // Guardar el motivo de cancelación en la base de datos
            if ($email && $cancellation_reason) {
                try {
                    // Obtener el ID de Stripe del cliente
                    $stripeCustomerId = $this->getStripeCustomerId($customer_id, $email);
                    
                    CancellationSurvey::create([
                        'customer_id' => $customer_id,
                        'stripe_customer_id' => $stripeCustomerId,
                        'email' => $email,
                        'reason' => $cancellation_reason,
                        'additional_comments' => $cancellation_comments,
                    ]);

                    \Log::info('Survey de cancelación guardado desde embed', [
                        'customer_id' => $customer_id,
                        'email' => $email,
                        'reason' => $cancellation_reason
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error guardando survey de cancelación desde embed: ' . $e->getMessage());
                }
            }

            // Actualizar custom fields en Baremetrics con el motivo y comentarios de cancelación
            if ($cancellation_reason) {
                try {
                    $baremetricsData = [
                        'cancellation_reason' => $cancellation_reason,
                    ];
                    
                    if (!empty($cancellation_comments)) {
                        $baremetricsData['cancellation_comments'] = $cancellation_comments;
                    }
                    
                    $updateResult = $this->baremetricsService->updateCustomerAttributes($customer_id, $baremetricsData);
                    
                    if ($updateResult) {
                        \Log::info('Custom fields de Baremetrics actualizados desde embed', [
                            'customer_id' => $customer_id,
                            'updated_fields' => array_keys($baremetricsData)
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error actualizando custom fields en Baremetrics desde embed: ' . $e->getMessage());
                }
            }

            // Registrar el motivo de cancelación en Barecancel Insights
            if ($cancellation_reason) {
                try {
                    $barecancelResult = $this->baremetricsService->recordCancellationReason(
                        $customer_id, 
                        $cancellation_reason, 
                        $cancellation_comments
                    );
                    
                    if ($barecancelResult) {
                        \Log::info('Motivo de cancelación registrado en Barecancel desde embed', [
                            'customer_id' => $customer_id,
                            'reason' => $cancellation_reason
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error registrando motivo en Barecancel desde embed: ' . $e->getMessage());
                }
            }

            // Actualizar el motivo de cancelación en GoHighLevel
            if ($email && $cancellation_reason) {
                try {
                    $ghlService = app(\App\Services\GoHighLevelService::class);
                    $ghlContact = $ghlService->getContactsByExactEmail($email);
                    
                    if (empty($ghlContact['contacts'])) {
                        $ghlContact = $ghlService->getContacts($email);
                    }
                    
                    if (!empty($ghlContact['contacts'])) {
                        $contactId = $ghlContact['contacts'][0]['id'];
                        
                        $customFields = [
                            'UhyA0ol6XoETLRA5jsZa' => $cancellation_reason,
                        ];
                        
                        if (!empty($cancellation_comments)) {
                            $customFields['zYi50QSDZC6eGqoRH8Zm'] = $cancellation_comments;
                        }
                        
                        $ghlService->updateContactCustomFields($contactId, $customFields);
                        \Log::info('Motivo de cancelación actualizado en GHL desde embed', [
                            'customer_id' => $customer_id,
                            'email' => $email
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error actualizando motivo en GHL desde embed: ' . $e->getMessage());
                }
            }
            
            // Cancelar la suscripción en Stripe
            // Obtener el ID de Stripe de la suscripción
            $stripeSubscriptionId = null;
            
            if (!empty($subscription_id)) {
                // Verificar si es un ID de Stripe (empieza con sub_)
                if (strpos($subscription_id, 'sub_') === 0) {
                    $stripeSubscriptionId = $subscription_id;
                } else {
                    // Es probablemente un OID de Baremetrics, buscar la suscripción en Stripe
                    try {
                        $allSubscriptions = \Stripe\Subscription::all([
                            'customer' => $customer_id,
                            'status' => 'all',
                            'limit' => 100
                        ]);
                        
                        foreach ($allSubscriptions->data as $stripeSubscription) {
                            if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                                $stripeSubscriptionId = $stripeSubscription->id;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error buscando suscripción en Stripe (legacy): ' . $e->getMessage());
                    }
                }
            }
            
            if ($stripeSubscriptionId) {
                try {
                    $stripeResult = $this->stripeService->cancelActiveSubscription($customer_id, $stripeSubscriptionId);
                    if (!$stripeResult['success']) {
                        \Log::error('Error cancelando suscripción en Stripe (legacy): ' . ($stripeResult['error'] ?? 'Error desconocido'));
                    }
                } catch (\Exception $e) {
                    \Log::error('Excepción al cancelar suscripción en Stripe (legacy): ' . $e->getMessage());
                }
            } else {
                \Log::warning('No se pudo determinar el ID de suscripción de Stripe para cancelar (legacy)', [
                    'customer_id' => $customer_id,
                    'subscription_id' => $subscription_id
                ]);
            }
            
            // Cancelar en Baremetrics también
            try {
                $sources = $this->baremetricsService->getSources();
                if ($sources) {
                    $sourceIds = [];
                    if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
                        $sourceIds = array_column($sources['sources'], 'id');
                    } elseif (is_array($sources)) {
                        $sourceIds = array_column($sources, 'id');
                    }

                    foreach ($sourceIds as $sourceId) {
                        // Intentar obtener el OID de la suscripción desde Baremetrics
                        try {
                            $subscriptions = $this->baremetricsService->getSubscriptions($sourceId, '', 1);
                            if ($subscriptions && isset($subscriptions['subscriptions'])) {
                                foreach ($subscriptions['subscriptions'] as $bmSubscription) {
                                    if (isset($bmSubscription['oid']) && $bmSubscription['oid'] === $subscription_id) {
                                        $this->baremetricsService->deleteSubscription($sourceId, $bmSubscription['oid']);
                                        break 2;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning('No se pudo cancelar en Baremetrics desde embed: ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error cancelando en Baremetrics desde embed: ' . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'message' => 'La suscripción ha sido cancelada correctamente.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al cancelar suscripción con embed: ' . $e->getMessage(), [
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

        // Rastrear que el usuario vio la encuesta (si no se rastreó antes)
        try {
            $email = session('cancellation_email') ?? ($customer['email'] ?? null);
            if ($email) {
                $tracking = CancellationTracking::where('email', $email)
                    ->latest()
                    ->first();
                
                if ($tracking && !$tracking->survey_viewed) {
                    $tracking->markSurveyViewed($customer_id);
                    \Log::info('Seguimiento de cancelación: encuesta vista (directo)', [
                        'email' => $email,
                        'customer_id' => $customer_id,
                        'tracking_id' => $tracking->id
                    ]);
                    
                    // Enviar correo de resumen a administradores
                    $this->sendCancellationSummaryEmail($tracking, 'survey_viewed');
                } elseif (!$tracking) {
                    // Crear nuevo registro si no existe
                    $tracking = CancellationTracking::create([
                        'email' => $email,
                        'customer_id' => $customer_id,
                    ]);
                    $tracking->markSurveyViewed($customer_id);
                    \Log::info('Seguimiento de cancelación: nuevo registro creado al ver encuesta', [
                        'email' => $email,
                        'customer_id' => $customer_id,
                        'tracking_id' => $tracking->id
                    ]);
                    
                    // Enviar correo de resumen a administradores
                    $this->sendCancellationSummaryEmail($tracking, 'survey_viewed');
                }
            }
        } catch (\Exception $trackingError) {
            \Log::error('Error al rastrear visualización de encuesta (directo)', [
                'customer_id' => $customer_id,
                'error' => $trackingError->getMessage()
            ]);
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
            // Obtener el ID de Stripe del cliente
            $stripeCustomerId = $this->getStripeCustomerId($customer_id, $email);
            
            CancellationSurvey::create([
                'customer_id' => $customer_id,
                'stripe_customer_id' => $stripeCustomerId,
                'email' => $email,
                'reason' => $reason,
                'additional_comments' => $additional_comments,
            ]);

            // Rastrear que el usuario completó la encuesta
            try {
                if ($email) {
                    $tracking = CancellationTracking::where('email', $email)
                        ->latest()
                        ->first();
                    
                    if ($tracking) {
                        $tracking->markSurveyCompleted($customer_id, $stripeCustomerId);
                        \Log::info('Seguimiento de cancelación: encuesta completada', [
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'tracking_id' => $tracking->id
                        ]);
                        
                        // Enviar correo de resumen a administradores
                        $this->sendCancellationSummaryEmail($tracking, 'survey_completed');
                    } else {
                        // Crear nuevo registro si no existe
                        $tracking = CancellationTracking::create([
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'stripe_customer_id' => $stripeCustomerId,
                        ]);
                        $tracking->markSurveyCompleted($customer_id, $stripeCustomerId);
                        \Log::info('Seguimiento de cancelación: nuevo registro creado al completar encuesta', [
                            'email' => $email,
                            'customer_id' => $customer_id,
                            'tracking_id' => $tracking->id
                        ]);
                        
                        // Enviar correo de resumen a administradores
                        $this->sendCancellationSummaryEmail($tracking, 'survey_completed');
                    }
                }
            } catch (\Exception $trackingError) {
                \Log::error('Error al rastrear completación de encuesta', [
                    'email' => $email,
                    'customer_id' => $customer_id,
                    'error' => $trackingError->getMessage()
                ]);
            }

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
                        
                        // Rastrear cancelación en Stripe
                        try {
                            if ($email) {
                                $tracking = CancellationTracking::where('email', $email)
                                    ->latest()
                                    ->first();
                                
                                if ($tracking) {
                                    $details = json_encode([
                                        'subscription_id' => $subscriptionId,
                                        'details' => $stripeResult['data'] ?? null
                                    ]);
                                    $tracking->markStripeCancelled($details);
                                    \Log::info('Seguimiento de cancelación: cancelado en Stripe', [
                                        'email' => $email,
                                        'customer_id' => $customer_id,
                                        'subscription_id' => $subscriptionId,
                                        'tracking_id' => $tracking->id
                                    ]);
                                    
                                    // Enviar correo de resumen a administradores
                                    $this->sendCancellationSummaryEmail($tracking, 'stripe_cancelled');
                                    
                                    // Verificar si el proceso está completo y enviar correo final
                                    $tracking->refresh(); // Recargar para obtener el estado actualizado
                                    if ($tracking->process_completed) {
                                        $this->sendCancellationSummaryEmail($tracking, 'process_completed');
                                    }
                                }
                            }
                        } catch (\Exception $trackingError) {
                            \Log::error('Error al rastrear cancelación en Stripe', [
                                'email' => $email,
                                'error' => $trackingError->getMessage()
                            ]);
                        }
                        
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
                                    
                                    // Rastrear cancelación en Baremetrics
                                    try {
                                        if ($email) {
                                            $tracking = CancellationTracking::where('email', $email)
                                                ->latest()
                                                ->first();
                                            
                                            if ($tracking) {
                                                $details = json_encode([
                                                    'subscription_oid' => $subscriptionData['subscription']['oid'],
                                                    'source_id' => $sourceId,
                                                    'subscription_id' => $subscriptionId
                                                ]);
                                                $tracking->markBaremetricsCancelled($details);
                                                \Log::info('Seguimiento de cancelación: cancelado en Baremetrics', [
                                                    'email' => $email,
                                                    'customer_id' => $customer_id,
                                                    'subscription_id' => $subscriptionId,
                                                    'tracking_id' => $tracking->id
                                                ]);
                                                
                                                // Enviar correo de resumen a administradores
                                                $this->sendCancellationSummaryEmail($tracking, 'baremetrics_cancelled');
                                                
                                                // Verificar si el proceso está completo y enviar correo final
                                                $tracking->refresh(); // Recargar para obtener el estado actualizado
                                                if ($tracking->process_completed) {
                                                    $this->sendCancellationSummaryEmail($tracking, 'process_completed');
                                                }
                                            }
                                        }
                                    } catch (\Exception $trackingError) {
                                        \Log::error('Error al rastrear cancelación en Baremetrics', [
                                            'email' => $email,
                                            'error' => $trackingError->getMessage()
                                        ]);
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
