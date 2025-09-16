<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Log;
use App\Services\GoHighLevelService;
use Illuminate\Http\Request;
use App\Models\Configuration;

class GoHighLevelController extends Controller
{
    public $ghl;
    
    public function __construct(GoHighLevelService $ghl) {
        $this->ghl = $ghl;
    }

    public function initial()
    {
        $url = config('services.gohighlevel.authorization_url');
        $clientId = config('services.gohighlevel.client_id');
        $scopes = config('services.gohighlevel.scopes');

        $url = "https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&loginWindowOpenMode=self&redirect_uri={$url}&client_id={$clientId}&scope={$scopes}";

        return redirect()->away($url);
    }

    public function authorization(Request $request)
    {
        $code = $request->get('code');
        
        try {
            // The getToken method already handles saving the token data in configuration
            // if a configuration record exists
            $token = $this->ghl->getToken($code);
            
            // Make sure we have a configuration record
            $conf = Configuration::first();
            if (!$conf) {
                // Create a new configuration if it doesn't exist
                $conf = new Configuration();
                $conf->ghl_code = $code;
                $conf->ghl_token = $token;
                $conf->save();
            }
            
            return redirect()->route('admin.dashboard')->with('success', 'Se obtuvo el código de autorización exitosamente');
        } catch (\Exception $e) {
            Log::error('Error in GHL authorization', ['error' => $e->getMessage()]);
            return redirect()->route('admin.dashboard')->with('error', 'Error al obtener autorización: ' . $e->getMessage());
        }
    }

    public function getCustomFields()
    {
        $custom = $this->ghl->getCustomFields();
        return response()->json($custom);
    }

    public function getContacts(Request $request)
    {
        $email = $request->get('email');
        $contacts = $this->ghl->getContacts($email);
        return response()->json($contacts);
    }
}
