<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\NashService;
use App\Services\StripeService;
use Illuminate\Http\Request;

class NashController extends Controller
{
    /**
     * Store a newly created order in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleJob(Request $request)
    {
        $nash_service = new NashService();
        $stripe_service = new StripeService();

        $body = $request->all();
        if (!isset($body['data']['id'])) {
            return response()->json(['message' => 'id not found.'], 404);
        }
        
        $details = $nash_service->fetchJobDetails($body['data']['id']);

        $order = Order::updateOrCreate(
            ['job_id' => $details['job_id']],
            [
                'email' => $details['email'],
                'status' => $details['status'],
                'distance' => $details['distance'],
                'standard_delivery_tip' => $details['standard_delivery_tip'],
                'delivery_date' => $details['delivery_date'],
                'delivery_start_time' => $details['delivery_start_time'],
                'delivery_end_time' => $details['delivery_end_time'],
                'pickup_address' => $details['pickup_address'],
                'pickup_name' => $details['pickup_name'],
                'asap' => $details['asap'],
                'dropoff_address' => $details['dropoff_address'],
                'dropoff_name' => $details['dropoff_name'],
                'delivery_style' => $details['delivery_style']
            ]
        );
        $updated_price = 
        $stripe_type = 
        $stripe_service->processStripe($details, 'Normal', $details['standard_delivery_tip']);

        return response()->json($order);
    }
}
