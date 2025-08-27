<?php

namespace App\Http\Controllers;
use Log;
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
        $token = $this->ghl->getToken($code);
        
        $conf = Configuration::first();
        $conf->ghl_code = $code;
        $conf->ghl_token = $token;
        $conf->save();

        return redirect()->route('admin.dashboard')->with('success', 'Se obtuvo el código de autorización exitosamente');
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
