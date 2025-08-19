<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;

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
    public function getAllCustomerIds()
    {
        $allCustomers = [];
        $startingAfter = null;
        $hasMore = true;

        try {
            while ($hasMore) {
                $result = $this->getCustomerIds(100, $startingAfter);
                
                if (!$result['success']) {
                    return $result;
                }

                $allCustomers = array_merge($allCustomers, $result['data']);
                
                if ($result['has_more'] && !empty($result['data'])) {
                    $startingAfter = end($result['data'])['id'];
                } else {
                    $hasMore = false;
                }
            }

            return [
                'success' => true,
                'data' => $allCustomers,
                'total_count' => count($allCustomers)
            ];

        } catch (\Exception $e) {
            Log::error('Error al obtener todos los customers de Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
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
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'created' => $customer->created,
                    'description' => $customer->description,
                    'metadata' => $customer->metadata,
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
    public function searchCustomersByEmail($email)
    {
        try {
            $customers = Customer::all([
                'email' => $email,
                'limit' => 100,
            ]);

            $customerData = [];
            foreach ($customers->data as $customer) {
                $customerData[] = [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'created' => $customer->created,
                ];
            }

            return [
                'success' => true,
                'data' => $customerData,
                'total_count' => count($customerData)
            ];

        } catch (ApiErrorException $e) {
            Log::error('Error al buscar customers por email en Stripe: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
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
}
