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
	 * @return \Illuminate\Http\Response
	 */
	public function index(OrderIndexRequest $request)
	{
		$validated = $request->validated();

		$page = $validated['page'] ?? 1;
		$perPage = $validated['per_page'] ?? 10;
		$email = $validated['email'] ?? null;
		$statuses = $validated['status'] ?? null;

		$query = Order::query();
		if ($validated['time'] !== null) {
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
		if ($statuses !== null) {
			$query->whereIn('status', $statuses);
		}

		if ($email !== null) {
			if (str_starts_with($email, '@')) {
				$query->where('email', 'LIKE', '%' . $email);
			} else {
				$query->where('email', $email);
			}
		}


		$orders = $query->paginate($perPage, ['*'], 'page', $page);
		$orders->getCollection()->transform(function ($order) {
			$order->makeHidden(['id']);
			$order->dropoff_window_end = $this->calculateDropoffWindowEnd($order);
			return $order;
		});

		return response()->json($orders);
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
		
		return response()->json($order, 204);
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
