<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    /**
     * Check the health of the service.
     * 
     * @return \Illuminate\Http\JsonResponse
     * The JSON response.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'service' => 'unihop-orders',
            'status' => 'ok'
        ]);
    }
}
