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
                ];

                // Actualizar en Baremetrics
                $result = $this->baremetricsService->updateCustomerAttributes($stripe_id, $ghlData);
                Log::info('Cliente actualizado con éxito desde GHL', ['result' => $result]);

            } else {
                return response()->json(['message' => 'No se encontro el contacto en GHL con el correo'], 404);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Invalid email format'], 400);
        }
    }
}