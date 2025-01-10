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
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'start' => 'nullable|integer|min:0',
            'end' => 'nullable|integer|gt:start',
            'status' => 'nullable|array',
            'status.*' => 'string|in:Created,Driver Pending,Assigning Driver,Assigned Driver,Pickup Enroute,Pickup Arrived,Dropoff Enroute,Dropoff Arrived,Delivered,Other,Canceled,Canceled Driver',
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
            'page.integer' => 'The page parameter must be an integer.',
            'page.min' => 'The page parameter must be at least 1.',
            'per_page.integer' => 'The per_page parameter must be an integer.',
            'per_page.min' => 'The per_page parameter must be at least 1.',
            'per_page.max' => 'The per_page parameter cannot exceed 100.',
            'start.integer' => 'The start parameter must be an integer.',
            'start.min' => 'The start parameter must be at least 0.',
            'end.integer' => 'The end parameter must be an integer.',
            'end.gt' => 'The end parameter must be greater than the start parameter.',
            'status.array' => 'The status parameter must be an array.',
            'status.*.in' => 'One or more statuses are invalid.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if (is_string($this->status)) {
            // Decode and split the string by commas
            $decodedStatus = urldecode($this->status);
            $splitStatus = explode(',', $decodedStatus);
            $splitStatus = array_map('trim', $splitStatus);
            
            $this->merge([
                'status' => $splitStatus,
            ]);
        }

        // Ensure that status is always treated as an array, even if passed multiple times
        if (is_array($this->status)) {
            $this->merge([
                'status' => array_map('trim', $this->status), // Trim any extra spaces
            ]);
        }
    }
}
