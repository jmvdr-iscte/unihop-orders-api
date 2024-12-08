<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    public function health() :JsonResponse
    {
        return response()->json([
            'service' => 'unihop-orders',
            'status' => 'Ok'
        ]);
    }
}
