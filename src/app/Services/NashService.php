<?php

namespace App\Services;

use App\Utils\PriceCalculator as UPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NashService
{
	//private const
	private const API_URL = "https://api.sandbox.usenash.com/v1/jobs";
	
	
	//final public methods
	/**
	 * Fetches the details of a job from Nash API.
	 *
	 * @param string $job_id 
	 * The job ID.
	 * 
	 * @return array 
	 * The job details.
	 * 
	 * @throws \Exception If the request is invalid.
	 */
	final public function fetchJobDetails(string $job_id): array
	{
		//request
		$response = Http::withHeaders([
			'Authorization' => 'Bearer ' . env('NASH_SECRET'),
			'Accept' => 'application/json',
		])->get(self::API_URL . '/' . $job_id);
	
		if (!$response->successful()) {
			Log::error('Invalid Nash get request', ['data' => $response]);
			throw new \Exception('Invalid Nash request');
		}
	
		//get data
		$data = $response->json();
		$status = $data['jobConfigurations'][0]['advancedTask']['delivery']['status'];
		$delivery_start_time = $data['jobConfigurations'][0]['package']['dropoffStartTime'] ?? null;
		$delivery_end_time = $data['jobConfigurations'][0]['package']['dropoffEndTime'] ?? null;
		$distance = ($data['jobConfigurations'][0]['package']['drivingMetrics']['distance'] ?? 0) / 1609.34;
 
		$option_id = $data['optionsGroup']['id'] ?? null;
		$package_delivery_mode = $data['jobConfigurations'][0]['package']['packageDeliveryMode'] ?? '';
		$time_zone = $data['jobConfigurations'][0]['package']['pickupLocation']['timezoneName'] ?? '';
		$time_zone = implode('', array_map(fn($x) => $x[0], explode(' ', $time_zone)));
	
		$pickup_address = preg_replace('/,\s*US(A)?$/', '', $data['jobConfigurations'][0]['package']['pickupLocation']['formattedAddress'] ?? '');
		$pickup_name = $data['jobConfigurations'][0]['package']['pickupLocation']['firstName'] ?? '';
	
		$dropoff_address = preg_replace('/,\s*US(A)?$/', '', $data['jobConfigurations'][0]['package']['dropoffLocation']['formattedAddress'] ?? '');
		$dropoff_name = trim(explode('- UniHop', $data['jobConfigurations'][0]['package']['dropoffLocation']['firstName'] ?? '')[0]);
	
		$tip = ($data['jobConfigurations'][0]['tasks'][0]['tipAmountCents'] ?? 0) / 100;
	   
		$delivery_style = $this->getDeliveryStyle($distance, $tip, $option_id);
		$price = UPrice::calculate($data, $distance, $tip, $option_id);

		//validate email
		$email = $data['jobConfigurations'][0]['package']['pickupLocation']['email'] ?? null;
		if ($email === null) {
			$email = $data['jobConfigurations'][0]['package']['pickupLocation']['instructions'];
			if ($email === null || isset($email) && $email === 'N/A' || empty($email))  {
				Log::error('Email must be filled', ['response' => $data]);
				throw new \Exception('Email must be filled.');
			}
			$email = trim(explode('-*&', $email)[1]);
		}

		//status
		$status = $this->mapStatus($status, $package_delivery_mode, $distance);
	
		$delivery_date = null;
		$delivery_time = null;
		$switch_start_end_flag = false;
		$asap = $package_delivery_mode === "NOW";
	
		if ($delivery_start_time) {
			$delivery_start_time = new \DateTime($delivery_start_time . 'Z');
			$delivery_date = $delivery_start_time->format('F d, Y');
			$delivery_time = $delivery_start_time->format('H:i:s');
		} elseif ($delivery_end_time) {
			$delivery_end_time = new \DateTime($delivery_end_time . 'Z');
			$delivery_date = $delivery_end_time->format('F d, Y');
			$switch_start_end_flag = true;
			$delivery_time = null;
		}
	
		//return
		return [
			'job_id' => $job_id,
			'email' => $email,
			'status' => $status,
			'distance' => round($distance, 2),
			'price' => $price,
			'tip' => $tip,
			'delivery_date' => $delivery_date,
			'delivery_start_time' => $switch_start_end_flag ? null : $delivery_time,
			'delivery_end_time' => $switch_start_end_flag ? $delivery_time : null,
			'asap' => $asap,
			'pickup_address' => $pickup_address,
			'pickup_name' => $pickup_name,
			'dropoff_address' => $dropoff_address,
			'dropoff_name' => $dropoff_name,
			'delivery_style' => $delivery_style,
			'option_id' => $option_id
		];
	}
	
	
	//private methods
	/**
	 * Maps the status of the delivery.
	 *
	 * @param string $status 
	 * The status of the delivery.
	 * 
	 * @param string $delivery_mode 
	 * The delivery mode of the delivery.
	 * 
	 * @param float $distance 
	 * The distance of the delivery.
	 * 
	 * @return string 
	 * The mapped status.
	 */
	private function mapStatus(string $status, string $delivery_mode, float $distance): string
	{
		if (in_array($status, ['CANCELED_BY_CUSTOMER', 'CANCELED_BY_PROVIDER', 'CANCELED_BY_NASH', 'EXPIRED'])) {
			return 'Canceled';
		}
	
		// Delivered status
		if ($status === 'DROPOFF_COMPLETE') {
			return 'Delivered';
		}
	
		// Pickup Arrived status
		if ($status === 'PICKUP_ARRIVED') {
			return 'Pickup Arrived';
		}
	
		// Dropoff Enroute status
		if (in_array($status, ['PICKUP_COMPLETE', 'DROPOFF_ENROUTE'])) {
			return 'Dropoff Enroute';
		}
	
		// Pickup Enroute status
		if ($status === 'PICKUP_ENROUTE') {
			return 'Pickup Enroute';
		}
	
		// Assigned Driver status
		if ($status === 'ASSIGNED_DRIVER') {
			return 'Assigned Driver';
		}
	
		// Dropoff Arrived status
		if ($status === 'DROPOFF_ARRIVED') {
			return 'Dropoff Arrived';
		}
	
		// Other statuses
		if (in_array($status, ['FAILED', 'CANCELED_BY_AUTO_REASSIGN', 'RETURNED', 'RETURN_IN_PROGRESS', 'RETURN_ARRIVED'])) {
			return 'Other';
		}
	
		if (in_array($status, ['CREATED', 'SCHEDULED', 'NOT_ASSIGNED_DRIVER'])) {
			if ($delivery_mode === 'NOW') {
				return $distance <= 20.0 ? 'Assigning Driver' : 'Driver Pending';
			} else {
				return 'Created';
			}
		}

		return 'Other';
	}
	
	/**
	 * Gets the delivery style.
	 *
	 * @param float $distance 
	 * The distance of the delivery.
	 * 
	 * @param float $tip 
	 * The tip of the delivery.
	 * 
	 * @param string|null $option_id [default=null]
	 * The option id of the delivery.
	 * 
	 * @return string 
	 * The delivery style.
	 */
	private function getDeliveryStyle(float $distance, float $tip, ?string $option_id = null): string
	{
		if ($option_id === "dss_5BR7K8") {
			return "Alcohol";
		} elseif (in_array($option_id, ["dss_UJ3brb", "dss_4jdjfF"])) {
			return "Batched";
		} elseif (in_array($option_id, ["dss_a6mpw3", "dss_FsNLzs"]) && $distance > 20.0) {
			return "Standard - Long";
		} elseif ($option_id === "dss_hfQWkR") {
			return "Special Handling (2)";
		} elseif ($option_id === "dss_XEWdAE") {
			return "Custom";
		} elseif ($option_id === "dss_d6tSpe") {
			return "Catering Pro";
		} elseif ($option_id === "dss_bN9XiB") {
			return "Hybrid";
		} elseif ($option_id === "dss_3oZQHv") {
			return "Prescription";
		} elseif (in_array($option_id, ["dss_7jSMmA", "dss_65ontq"])) {
			return "Special Handling";
		} elseif (in_array($option_id, ["opn_836HQA", "dss_PsCM3y"])) {
			return "Oversize";
		} elseif ($tip <= 3) {
			return "Standard LCF";
		} else {
			return "Standard";
		}
	}
}
