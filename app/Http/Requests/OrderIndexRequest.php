<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderIndexRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'start' => 'nullable|integer|min:0',
			'end' => 'nullable|integer|gt:start',
			'status' => 'nullable|string|in:Created,Pending Driver,Assigning Driver,Assigned Driver,Pickup Enroute,Pickup Arrived,Dropoff Enroute,Dropoff Arrived,Delivered,Other'
		];
	}

	/**
	 * Custom messages for validation errors.
	 *
	 * @return array
	 */
	public function messages()
	{
		return [
			'start.integer' => 'The start parameter must be an integer.',
			'start.min' => 'The start parameter must be at least 0.',
			'end.integer' => 'The end parameter must be an integer.',
			'end.gt' => 'The end parameter must be greater than the start parameter.',
			'status.in' => 'Invalid status.',
		];
	}
}
