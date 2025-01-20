<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Http\Requests\OrderIndexRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class OrderController extends Controller
{
	/**
	 * Display a listing of orders.
	 *
	 * @param  \App\Http\Requests\OrderIndexRequest $request
	 * The request object.
	 * 
	 * @return \Illuminate\Http\JsonResponse
	 * The JSON response.
	 */
	public function index(OrderIndexRequest $request): JsonResponse
	{
		//validate request
		$validated = $request->validated();

		$page = $validated['page'] ?? 1;
		$perPage = $validated['per_page'] ?? 10;
		$email = $validated['email'] ?? null;
		$statuses = $validated['status'] ?? null;

		$query = Order::query();

		//time filter
		if (isset($validated['time'])) {
			$time = Carbon::now();
			if ($validated['time'] === 'today') {
				$query->whereDate('delivery_date', $time->toDateString());
			} elseif ($validated['time'] === 'past') {
				$query->whereDate('delivery_date', '<', $time->toDateString());
			} elseif ($validated['time'] === 'future') {
				$query->whereDate('delivery_date', '>', $time->toDateString());
			}
		}

		$query->orderBy('delivery_date', 'desc');

		//status filter
		if ($statuses !== null) {
			$query->whereIn('status', $statuses);
		}

		//email filter
		if ($email !== null) {
			if (str_starts_with($email, '@')) {
				$query->where('email', 'LIKE', '%' . $email);
			} else {
				$query->where('email', $email);
			}
		}

		//get orders
		$orders = $query->paginate($perPage, ['*'], 'page', $page);
		$orders->getCollection()->transform(function ($order) {
			$order->makeHidden(['id']);
			$order->dropoff_window_end = $this->calculateDropoffWindowEnd($order);
			return $order;
		});

		return response()->json($orders);
	}

	/**
	 * Update the specified order in storage.
	 * 
	 * @param \App\Http\Requests\OrderRequest $request
	 * The request object.
	 * 
	 * @param string $job_id
	 * The job id.
	 * 
	 * @return \Illuminate\Http\JsonResponse
	 * The JSON response.
	 */
	public function update(OrderRequest $request, string $job_id): JsonResponse
	{
		//fetch order
		$order = Order::where('job_id', $job_id)->first();
		if ($order === null) {
			return response()->json([
				'error' => 'Not Found',
				'message' => "Order with uid {$job_id} not found.",
				'status' => 404,
			], 404);
		}
		$body = $request->validated();
		
		//update order
		$order->update($body);
		
		return response()->json($order, 204);
	}


	//private functions
	/**
	 * Calculate the dropoff window end time.
	 * 
	 * @param \App\Models\Order $order
	 * The order object.
	 * 
	 * @return string|null
	 * The dropoff window end time.
	 */
	private function calculateDropoffWindowEnd(Order $order): ?string
	{
		if ($order->asap) {
			return null;
		}

		$deliveryTime = Carbon::parse($order->delivery_date);
		$timeToAdd = match (strtolower($order->delivery_style)) {
			'special handling', 'oversize' => $order->distance <= 20 ? 20 : 30,
			'hybrid' => $order->distance <= 15 ? 20 : ($order->distance <= 20 ? 40 : 60),
			'custom', 'standard lcf' => $order->distance <= 5 ? 20 : 60,
			default => $order->distance <= 15 ? 20 : 60,
		};

		return $deliveryTime->addMinutes($timeToAdd)->format('g:i A');
	}
}
