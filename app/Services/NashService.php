<?php

namespace App\Services;

use App\Utils\PriceCalculator as UPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NashService
{
    private const API_URL = "https://api.sandbox.usenash.com/v1/jobs";
    
    public function testByJobId()
    {
        $jobId = "job_Uh7d8nU4gMSb5wz3S2LTof";
        $jobDetails = $this->fetchJobDetails($jobId);
        Log::info('Job Details:', $jobDetails);
    }

    public function fetchJobDetails(string $job_id): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('NASH_SECRET'),
            'Accept' => 'application/json',
        ])->get(self::API_URL . '/' . $job_id);
    
        if (!$response->successful()) {
            Log::error('Invalid Nash get request', ['data' => $response]);
            throw new \Exception('Invalid Nash request');
        }
    
        
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
    
        //TODO test this
        $tip = ($data['selectedConfiguration']['tasks'][0]['tipAmountCents'] ?? 0) / 100;
        
        $delivery_style = $this->getDeliveryStyle($distance, $tip, $option_id);
        $price = UPrice::calculate($data, $distance, $option_id);
    
        $email = $data['jobConfigurations'][0]['package']['pickupLocation']['instructions'];
        if ($email === null || isset($email) && $email === 'N/A')  {
            Log::error('Email must be filled', ['response' => $data]);
            throw new \Exception('Email must be filled.');
        }
    
        try {
            $email = trim(explode('-*&', $email)[1]);
        } catch (\Exception $e) {
            $email = trim(explode('-*', $email)[1]);
        }
    
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
    
        return [
            'job_id' => $job_id,
            'email' => $email,
            'status' => $status,
            'distance' => round($distance, 2),
            'standard_delivery_tip' => $price,
            'delivery_date' => $delivery_date,
            'delivery_start_time' => $switch_start_end_flag ? null : $delivery_time,
            'delivery_end_time' => $switch_start_end_flag ? $delivery_time : null,
            'asap' => $asap,
            'pickup_address' => $pickup_address,
            'pickup_name' => $pickup_name,
            'dropoff_address' => $dropoff_address,
            'dropoff_name' => $dropoff_name,
            'delivery_style' => $delivery_style,
        ];
    }
    

    private function mapStatus(string $status, string $delivery_mode, float $distance): string
    {
        if (in_array($status, ['CANCELED_BY_CUSTOMER', 'CANCELED_BY_PROVIDER', 'CANCELED_BY_NASH', 'EXPIRED'])) {
            return 'Canceled';
        }

        if ($status === 'DROPOFF_COMPLETE') {
            return 'Delivered';
        }

        if ($status === 'PICKUP_ENROUTE') {
            return 'Pickup Enroute';
        }

        if (in_array($status, ['CREATED', 'SCHEDULED', 'NOT_ASSIGNED_DRIVER'])) {
            return $delivery_mode === 'NOW' && $distance <= 20.0 ? 'Assigning Driver' : 'Driver Pending';
        }

        return 'Other';
    }
    
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
        } elseif ($tip <= 2) {
            return "Standard LCF";
        } else {
            return "Standard";
        }
    }
}
