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

            $ghl_customer = $this->ghlService->getContacts($request->email);
            $stripeCustomer = $this->stripeService->searchCustomersByEmail($request->email);
            
            $stripe_id = $stripeCustomer['data'][0]['id'] ?? null;
            
            $subscription = $this->ghlService->getSubscriptionStatusByContact($ghl_customer['contacts'][0]['id'] ?? '');

            $couponCode = $subscription['couponCode'] ?? null;
            $subscription_status = $subscription['status'] ?? 'none';

            if (!empty($ghl_customer['contacts']) && $stripeCustomer['data']) {
                $customFields = collect($ghl_customer['contacts'][0]['customFields']);
                $country = $ghl_customer['contacts'][0]['country'] ?? '-';
                $city = $ghl_customer['contacts'][0]['city'] ?? '-';
                $score = $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D');
                $birthplace = $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG');
                $sign = $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo');
                $hasKids = $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c');
                $isMarried = $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1');

                $ghlData = [
                    'relationship_status' => $isMarried['value'] ?? '-',
                    'community_location' => $birthplace['value'] ?? '-',
                    'country' => $country ?? '-',
                    'engagement_score' => $score['value'] ?? '-',
                    'has_kids' => $hasKids['value'] ?? '-',
                    'state' => $ghl_customer['contacts'][0]['state'] ?? '-',
                    'location' => $city,
                    'zodiac_sign' => $sign['value'] ?? '-',
                    'subscriptions' => $subscription_status ?? 'none',
                    'coupon_code' => $couponCode ?? null
                ];

                return response()->json(['message' => 'GHL Data', 'ghl_customer' => $ghlData], 200);

                // Actualizar en Baremetrics
                $result = $this->baremetricsService->updateCustomerAttributes($stripe_id, $ghlData);

                return response()->json([
                    'message' => 'Actualización exitosa',
                    'result' => $result
                ], 200);
                
                Log::info('Cliente actualizado con éxito desde GHL', ['result' => $result]);

            } else {
                return response()->json(['message' => 'No se encontro el contacto en GHL con el correo'], 404);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid email format'], 400);
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