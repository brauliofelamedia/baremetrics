<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use \Stripe\Subscription;

class StripeService
{
    protected $stripe;

    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Verificar la conectividad con Stripe API
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            // Hacer una llamada mínima para verificar conectividad
            $customers = Customer::all(['limit' => 1]);
            
            return [
                'success' => true,
                'message' => 'Conectado exitosamente a Stripe API'
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error de conectividad con Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Error inesperado al conectar con Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conectividad inesperado'
            ];
        }
    }

    /**
     * Obtener todos los customer IDs de Stripe
     *
     * @param int $limit Límite de customers a obtener (default: 100)
     * @param string|null $startingAfter ID para paginación
     * @return array
     */
    public function getCustomerIds($limit = 100, $startingAfter = null)
    {
        try {
            $params = [
                'limit' => $limit,
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            // Configurar timeout para esta operación específica
            set_time_limit(30); // 30 segundos máximo para esta operación
            
            $customers = Customer::all($params);
            
            $customerIds = [];
            foreach ($customers->data as $customer) {
                $customerIds[] = [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'created' => $customer->created,
                    'description' => $customer->description,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'shipping' => $customer->shipping,
                    'currency' => $customer->currency,
                    'balance' => $customer->balance,
                    'delinquent' => $customer->delinquent,
                    'default_source' => $customer->default_source,
                    'invoice_prefix' => $customer->invoice_prefix,
                    'metadata' => $customer->metadata,
                    'tax_exempt' => $customer->tax_exempt,
                    'preferred_locales' => $customer->preferred_locales,
                ];
            }

            return [
                'success' => true,
                'data' => $customerIds,
                'has_more' => $customers->has_more,
                'total_count' => count($customerIds)
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error al obtener customers de Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Error general al obtener customers de Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error de conectividad o timeout',
                'data' => []
            ];
        }
    }

    /**
     * Obtener todos los customer IDs con paginación automática
     *
     * @return array
     */
    /**
     * Obtener todos los customer IDs de Stripe sin límite
     *
     * @return array
     */
    public function getAllCustomerIds()
    {
        $allCustomers = [];
        $startingAfter = null;
        $hasMore = true;
        $maxIterations = 100; // Prevenir bucles infinitos
        $currentIteration = 0;

        try {
            // Verificar conectividad primero
            $testConnection = $this->testConnection();
            if (!$testConnection['success']) {
                return [
                    'success' => false,
                    'error' => 'Error de conectividad: ' . $testConnection['error'],
                    'data' => []
                ];
            }

            while ($hasMore && $currentIteration < $maxIterations) {
                $currentIteration++;
                
                // Establecer un timeout para cada iteración
                set_time_limit(30);
                
                $result = $this->getCustomerIds(100, $startingAfter);
                
                if (!$result['success']) {
                    Log::error('Error en iteración ' . $currentIteration . ' de getAllCustomerIds', [
                        'error' => $result['error'],
                        'starting_after' => $startingAfter
                    ]);
                    return $result;
                }

                $allCustomers = array_merge($allCustomers, $result['data']);
                
                if ($result['has_more'] && !empty($result['data'])) {
                    $startingAfter = end($result['data'])['id'];
                } else {
                    $hasMore = false;
                }

                // Pausa pequeña para evitar rate limiting
                if ($hasMore) {
                    usleep(100000); // 0.1 segundos
                }
            }

            if ($currentIteration >= $maxIterations) {
                Log::warning('getAllCustomerIds alcanzó el límite máximo de iteraciones', [
                    'max_iterations' => $maxIterations,
                    'customers_loaded' => count($allCustomers)
                ]);
            }

            return [
                'success' => true,
                'data' => $allCustomers,
                'total_count' => count($allCustomers)
            ];

        } catch (\Exception $e) {
            Log::error('Error inesperado al obtener todos los customers de Stripe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'iteration' => $currentIteration ?? 0,
                'customers_loaded' => count($allCustomers)
            ]);
            
            return [
                'success' => false,
                'error' => 'Error inesperado: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Obtener un customer específico por ID
     *
     * @param string $customerId
     * @return array
     */
    public function getCustomer($customerId)
    {
        try {
            $customer = Customer::retrieve($customerId);
            
            return [
                'success' => true,
                'data' => [
                    'id' => $customer->id,
                    'object' => $customer->object,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'created' => $customer->created,
                    'description' => $customer->description,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'shipping' => $customer->shipping,
                    'currency' => $customer->currency,
                    'balance' => $customer->balance,
                    'delinquent' => $customer->delinquent,
                    'default_source' => $customer->default_source,
                    'invoice_prefix' => $customer->invoice_prefix,
                    'invoice_settings' => $customer->invoice_settings,
                    'livemode' => $customer->livemode,
                    'metadata' => $customer->metadata,
                    'tax_exempt' => $customer->tax_exempt,
                    'test_clock' => $customer->test_clock,
                    'preferred_locales' => $customer->preferred_locales,
                ]
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error al obtener customer de Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Buscar customers por email
     *
     * @param string $email
     * @return array
     */
    /**
     * Buscar customers por email en Stripe
     *
     * @param string $email Email del customer a buscar
     * @return array
     */
    public function searchCustomersByEmail($email)
    {
        try {
            // Limpiar y normalizar el email
            $email = trim(strtolower($email));
            
            // Validar el formato del email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'El formato del email no es válido',
                    'data' => []
                ];
            }

            // Buscar customers por email en Stripe
            $customers = Customer::all([
                'email' => $email,
                'limit' => 100,
            ]);

            $customerData = [];
            foreach ($customers->data as $customer) {
                
                $subscriptions = \Stripe\Subscription::all([
                    'customer' => $customer->id,
                    'status' => 'active',
                    'limit' => 50
                ]);

                $customerData[] = [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'created' => $customer->created,
                    'description' => $customer->description,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'shipping' => $customer->shipping,
                    'currency' => $customer->currency,
                    'balance' => $customer->balance,
                    'delinquent' => $customer->delinquent,
                    'default_source' => $customer->default_source,
                    'invoice_prefix' => $customer->invoice_prefix,
                    'metadata' => $customer->metadata,
                    'tax_exempt' => $customer->tax_exempt,
                    'preferred_locales' => $customer->preferred_locales,
                    'subscriptions' => $subscriptions->data,
                ];
            }

            return [
                'success' => true,
                'data' => $customerData,
                'total_count' => count($customerData)
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error al buscar customers por email en Stripe API', [
                'email' => $email ?? 'N/A',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return [
                'success' => false,
                'error' => 'Error de la API de Stripe: ' . $e->getMessage(),
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Error inesperado al buscar customers por email', [
                'email' => $email ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Error inesperado durante la búsqueda',
                'data' => []
            ];
        }
    }

    /**
     * Obtener la clave publishable para el frontend
     *
     * @return string
     */
    public function getPublishableKey()
    {
        return config('services.stripe.publishable_key');
    }

        /**
     * Cancelar la suscripción activa de un usuario en Stripe
     *
     * @param string $customerId
     * @param string $subscriptionId
     * @return array
     */
    public function cancelActiveSubscription($customerId, $subscriptionId)
    {
        try {
            // Validar que customerId no esté vacío
            if (empty($customerId)) {
                return [
                    'success' => false,
                    'error' => 'El ID del cliente es requerido.'
                ];
            }

            $subscription = null;

            // Si se proporciona subscriptionId, usarlo directamente
            if (!empty($subscriptionId)) {
                try {

                    $subscription = Subscription::retrieve($subscriptionId);

                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Suscripción no encontrada

                    Log::error('No se encontró la suscripción en Stripe', [
                        'customer_id' => $customerId,
                        'subscription_id' => $subscriptionId,
                        'stripe_error' => $e->getMessage()
                    ]);

                    return [
                        'success' => false,
                        'error' => 'No se encontró la suscripción en Stripe: ' . $e->getMessage()
                    ];
                }

                // Verificar que la suscripción pertenezca al cliente
                if ($subscription->customer !== $customerId) {
                    return [
                        'success' => false,
                        'error' => 'La suscripción no pertenece al cliente especificado.'
                    ];
                }
                
                // Verificar que la suscripción esté activa
                if (!in_array($subscription->status, ['active', 'trialing'])) {
                    return [
                        'success' => false,
                        'error' => 'La suscripción no está activa. Estado actual: ' . $subscription->status
                    ];
                }
            }

            // Cancelar al final del período
            $canceledSubscription = Subscription::update(
                $subscription->id,
                ['cancel_at_period_end' => true]
            );

            // Preparar respuesta informativa
            $response = [
                'success' => true,
                'data' => [
                    'id' => $canceledSubscription->id,
                    'status' => $canceledSubscription->status,
                    'cancel_at_period_end' => $canceledSubscription->cancel_at_period_end,
                    'current_period_end' => $canceledSubscription->current_period_end,
                    'cancellation_details' => [
                        'method' => 'end_of_period',
                        'cancellation_date' => date('Y-m-d H:i:s'),
                        'period_end_date' => date('Y-m-d H:i:s', $canceledSubscription->current_period_end)
                    ]
                ]
            ];

            return $response;

        } catch (\Stripe\Exception\ApiErrorException $e) {

            $errorMessage = 'Error de API al cancelar suscripción en Stripe: ' . $e->getMessage();
            
            Log::error($errorMessage, [
                'customer_id' => $customerId,
                'subscription_id' => $subscriptionId ?? 'not_provided'
            ]);
            
            return [
                'success' => false,
                'error' => 'Error al procesar la cancelación: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            $errorMessage = 'Error inesperado al cancelar suscripción: ' . $e->getMessage();
            Log::error($errorMessage, [
                'customer_id' => $customerId,
                'subscription_id' => $subscriptionId ?? 'not_provided'
            ]);
            
            return [
                'success' => false,
                'error' => 'Error inesperado al procesar la cancelación: ' . $e->getMessage()
            ];
        }
    }

    public function getSubscriptionCustomer($customer_id, $priceId)
    {
         $subscriptions = \Stripe\Subscription::all([
            'customer' => $customer_id,
            'limit' => 100,
            'status' => 'active'
        ]);

        foreach ($subscriptions->data as $subscription) {
            foreach ($subscription->items->data as $item) {
                if ($item->price->id === $priceId) {
                    return $subscription;
                }
            }
        }
    }

    public function checkSubscriptionCancellationStatus($subscriptionId)
    {
        $subscription = \Stripe\Subscription::retrieve($subscriptionId);
        return $subscription->cancel_at;
    }
}
