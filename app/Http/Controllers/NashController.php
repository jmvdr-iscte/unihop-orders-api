<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\NashService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NashController extends Controller
{
	/**
	 * Store a newly created order in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function handleJob(Request $request): JsonResponse
	{
		$nash_service = new NashService();
		$stripe_service = new StripeService();

		$body = $request->all();
		if (!isset($body['data']['id'])) {
			return response()->json(['message' => 'id not found.'], 404);
		}
		
		$details = $nash_service->fetchJobDetails($body['data']['id']);

		$order = Order::where('job_id', $details['job_id'])->first();
		$stripe_service->postProcessUpdate($details, $order);
		return response()->statud;
	}
}
