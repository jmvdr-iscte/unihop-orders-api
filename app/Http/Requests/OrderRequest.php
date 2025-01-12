<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
	 */
	public function rules(): array
	{
		return [
			'job_id' => 'sometimes|uuid',
			'email' => 'sometimes|email',
			'status' => 'sometimes|in:Created,Pending Driver,Assigning Driver,Assigned Driver,Pickup Enroute,Pickup Arrived,Dropoff Enroute,Dropoff Arrived,Delivered,Other',
			'delivery_date' => 'nullable|date',
			'delivery_start_time' => 'nullable|date_format:H:i:s',
			'delivery_end_time' => 'nullable|date_format:H:i:s',
			'pickup_address' => 'sometimes|string',
			'pickup_name' => 'sometimes|string',
			'dropoff_address' => 'sometimes|string',
			'dropoff_name' => 'sometimes|string',
			'distance' => 'sometimes|numeric|min:0',
			'asap' => 'sometimes|boolean',
			'stripe_processed' => 'sometimes|boolean',
			'price' => 'sometimes|numeric|min:0',
			'tip' => 'sometimes|numeric|min:0',
			'delivery_style' => 'sometimes|in:Standard,Standard - Long,Hybrid,Special Handling,Oversize,Standard LCF,Custom,Catering Pro'
		];
	}
}
