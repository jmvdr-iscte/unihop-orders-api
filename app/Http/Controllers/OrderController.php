<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Http\Requests\OrderIndexRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
	/**
	 * Display a listing of orders.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(OrderIndexRequest $request)
	{
		$validated = $request->validated();

		$start = $validated['start'] ?? 0;
		$end = $validated['end'] ?? ($start + 10);
		$status = $validated['status'] ?? null;

		$limit = max(0, $end - $start);

		$query = Order::query();

		if ($status !== null) {
			$query->where('status', $status);
		}

		$orders = $query->skip($start)->take($limit)->get();

		$orders->makeHidden(['id']);

		return response()->json($orders);
	}

	/**
	 * Store a newly created order in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		$validated = $request->validate([
			'job_id' => 'required|uuid',
			'email' => 'required|email',
			'status' => 'required|in:Created,Pending Driver,Assigning Driver,Assigned Driver,Pickup Enroute,Pickup Arrived,Dropoff Enroute,Dropoff Arrived,Delivered,Other',
			'delivery_date' => 'date',
			'delivery_start_time' => 'nullable|date_format:H:i',
			'delivery_end_time' => 'nullable|date_format:H:i',
			'pickup_address' => 'required|string',
			'pickup_name' => 'required|string',
			'dropoff_address' => 'required|string',
			'dropoff_name' => 'required|string',
			'distance' => 'required|numeric|min:0',
			'standard_delivery_tip' => 'required|numeric|min:0',
			'delivery_style' => 'required|in:Standard,Standard - Long,Hybrid,Special Handling,Oversize,Standard LCF,Custom,Catering Pro',
			'asap' => 'nullable|boolean',
		]);

		$order = Order::create($validated);

		return response()->json($order, 201);
	}

	/**
	 * Display the specified order.
	 *
	 * @param  \App\Models\Order  $order
	 * @return \Illuminate\Http\Response
	 */
	public function show(Order $order)
	{
		return response()->json($order);
	}

	/**
	 * Update the specified order in storage.
	 */
	public function update(OrderRequest $request, string $job_id): JsonResponse
	{
		$order = Order::where('job_id', $job_id)->first();

		if ($order === null) {
			return response()->json([
				'error' => 'Not Found',
				'message' => "Order with uid {$job_id} not found.",
				'status' => 404,
			], 404);
		}
		$body = $request->validated();
		$order->update($body);

		//TODO: add stripe
		return response()->json($order);
	}

	/**
	 * Remove the specified order from storage.
	 *
	 * @param  \App\Models\Order  $order
	 * @return \Illuminate\Http\Response
	 */
	public function destroy(Order $order)
	{
		$order->delete();

		return response()->json(['message' => 'Order deleted successfully'], 200);
	}

	/**
	 * Filter orders by status.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function filterByStatus(Request $request)
	{
		$validated = $request->validate([
			'status' => 'required|in:pending,in_progress,delivered,cancelled',
		]);

		$orders = Order::where('status', $validated['status'])->get();

		return response()->json($orders);
	}
}
