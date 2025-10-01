<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use App\Services\StripeService;
use Log;

class GoHighLevelController extends Controller
{
    protected $ghlService;
    protected $baremetricsService;
    protected $stripeService;

    public function __construct(GoHighLevelService $ghlService, BaremetricsService $baremetricsService, StripeService $stripeService)
    {
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
        $this->stripeService = $stripeService;
    }

    public function updateCustomerFromGHL(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;
            
            Log::info('Iniciando actualización de cliente desde GHL via API', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Buscar usuario en GoHighLevel con búsqueda mejorada
            $ghl_customer = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con contains
            if (empty($ghl_customer['contacts'])) {
                $ghl_customer = $this->ghlService->getContacts($email);
            }

            if (empty($ghl_customer['contacts'])) {
                Log::warning('Contacto no encontrado en GoHighLevel', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el contacto en GoHighLevel con el correo proporcionado',
                    'email' => $email
                ], 404);
            }

            $contact = $ghl_customer['contacts'][0];
            $contactId = $contact['id'];

            // Buscar cliente en Stripe
            $stripeCustomer = $this->stripeService->searchCustomersByEmail($email);
            
            if (empty($stripeCustomer['data'])) {
                Log::warning('Cliente no encontrado en Stripe', ['email' => $email]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el cliente en Stripe con el correo proporcionado',
                    'email' => $email
                ], 404);
            }

            $stripe_id = $stripeCustomer['data'][0]['id'];
            
            // Obtener datos de suscripción más reciente (mejorado)
            $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
            $couponCode = $subscription['couponCode'] ?? null;
            $subscription_status = $subscription['status'] ?? 'none';

            // Obtener campos personalizados
            $customFields = collect($contact['customFields'] ?? []);
            
            // Preparar datos para Baremetrics (mejorado)
            $ghlData = [
                'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
                'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
                'country' => $contact['country'] ?? '-',
                'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
                'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
                'state' => $contact['state'] ?? '-',
                'location' => $contact['city'] ?? '-',
                'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
                'subscriptions' => $subscription_status,
                'coupon_code' => $couponCode
            ];

            Log::debug('Datos preparados para Baremetrics', [
                'email' => $email,
                'contact_id' => $contactId,
                'stripe_id' => $stripe_id,
                'subscription_id' => $subscription['id'] ?? 'N/A',
                'subscription_status' => $subscription_status,
                'coupon_code' => $couponCode,
                'ghl_data' => $ghlData
            ]);

            // Actualizar en Baremetrics
            $result = $this->baremetricsService->updateCustomerAttributes($stripe_id, $ghlData);

            if ($result) {
                Log::info('Cliente actualizado con éxito desde GHL via API', [
                    'email' => $email,
                    'contact_id' => $contactId,
                    'stripe_id' => $stripe_id,
                    'subscription_status' => $subscription_status,
                    'coupon_code' => $couponCode,
                    'result' => $result
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Actualización exitosa',
                    'data' => [
                        'email' => $email,
                        'contact_id' => $contactId,
                        'stripe_id' => $stripe_id,
                        'subscription_status' => $subscription_status,
                        'coupon_code' => $couponCode,
                        'subscription_id' => $subscription['id'] ?? null,
                        'subscription_created_at' => $subscription['createdAt'] ?? null,
                        'updated_fields' => array_keys(array_filter($ghlData, function($value) {
                            return $value !== '-' && $value !== null && $value !== '';
                        }))
                    ],
                    'baremetrics_result' => $result
                ], 200);
            } else {
                Log::error('Error al actualizar en Baremetrics', [
                    'email' => $email,
                    'stripe_id' => $stripe_id,
                    'ghl_data' => $ghlData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar los datos en Baremetrics',
                    'email' => $email
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en API GHL', [
                'errors' => $e->errors(),
                'email' => $request->email ?? 'N/A'
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Formato de email inválido',
                'details' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            Log::error('Error general en API GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'N/A'
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatusMembershipGHL(Request $request)
    {
        try {
            $request->validate([
                'contactId' => 'required|string',
            ]);

            $contactId = $request->contactId;
            
            // Obtenemos la información de membresía del contacto
            $membership = $this->ghlService->getContactMembership($contactId);
            
            if (!$membership || empty($membership['memberships'])) {
                return response()->json([
                    'message' => 'No se encontró información de membresía para este contacto',
                    'status' => 'no_membership'
                ], 404);
            }
            
            // Obtenemos la membresía activa (si hay varias, tomamos la primera)
            $activeMembership = null;
            foreach ($membership['memberships'] as $m) {
                if (isset($m['status'])) {
                    $activeMembership = $m;
                    break;
                }
            }
            
            if (!$activeMembership) {
                return response()->json([
                    'message' => 'No se encontró membresía activa para este contacto',
                    'status' => 'inactive'
                ], 404);
            }
            
            // Buscamos el cliente en Stripe por GHL contactId
            $contact = $this->ghlService->getContacts($contactId);
            if (!$contact || empty($contact['contacts'])) {
                return response()->json([
                    'message' => 'No se encontró el contacto en GoHighLevel',
                ], 404);
            }
            
            // Obtenemos el email para buscar en Stripe
            $email = $contact['contacts'][0]['email'] ?? null;
            if (!$email) {
                return response()->json([
                    'message' => 'El contacto no tiene email asociado',
                ], 400);
            }
            
            $stripeCustomer = $this->stripeService->searchCustomersByEmail($email);
            $stripe_id = $stripeCustomer['data'][0]['id'] ?? null;
            
            if (!$stripe_id) {
                return response()->json([
                    'message' => 'No se encontró el cliente en Stripe',
                ], 404);
            }
            
            // Preparamos los datos para actualizar en Baremetrics
            $ghlData = [
                'membership_status' => $activeMembership['status']
            ];
            
            // Actualizamos los atributos del cliente en Baremetrics
            $result = $this->baremetricsService->updateCustomerAttributes($stripe_id, $ghlData);
            
            Log::info('Estado de membresía actualizado en Baremetrics', [
                'contact_id' => $contactId,
                'stripe_id' => $stripe_id,
                'membership_status' => $activeMembership['status'],
                'result' => $result
            ]);
            
            return response()->json([
                'message' => 'Estado de membresía actualizado con éxito',
                'membership' => $activeMembership,
                'baremetrics_update' => $result
            ], 200);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de membresía', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar estado de membresía: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSubscriptionbyEmail(Request $request)
    {
        $contactId = $request->input('contactId');
        if (!$contactId) {
            return response()->json(['error' => 'contactId is required'], 400);
        }
        $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
        return response()->json($subscription);
    }
}