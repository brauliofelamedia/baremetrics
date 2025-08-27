<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GoHighLevelController extends Controller
{
    public function updateCustomerFromGHL(Request $request)
    {
        return response()->json([
            'message' => 'Operación exitosa',
            'data' => $request->all()
        ]);
    }
}
