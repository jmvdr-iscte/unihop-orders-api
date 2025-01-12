<?php

namespace App\Utils;

class PriceCalculator
{
	final public static function calculate(array $data, float $distance, float $tip, ?string $option_id = null): string
	{
		$total_amount_cents = $data["jobConfigurations"][0]["advancedTask"]["delivery"]["totalPriceCents"] / 100;
		$price = 0.00;

		if (in_array($option_id, ["dss_7jSMmA", "dss_65ontq"])) {
			$distance_price = 35 + ceil($distance) * 0.5 + $tip;
			$total_price = ceil($total_amount_cents * 1.15) + $tip;
			$price = $distance_price >= $total_price ? $distance_price : $total_price;
		} elseif (in_array($option_id, ["dss_bN9XiB", "dsr_cv2WbL", "dss_d6tSpe"])) {
			$distance_price = 25 + ceil($distance) * 0.5 + $tip;
			$total_price = ceil($total_amount_cents * 1.15) + $tip;
			$price = $distance_price >= $total_price ? $distance_price : $total_price;
		} elseif (in_array($option_id, ["opn_836HQA", "dss_hfQWkR", "dss_PsCM3y"])) {
			$price = ceil($total_amount_cents * 1.15) + $tip;
		} elseif (in_array($option_id, ["dss_4jdjfF", "dss_UJ3brb"])) {
			if (ceil($distance) <= 20) {
				$price = 13 + $tip;
			} else {
				$miles_over_twenty = ceil($distance) - 20;
				$price = 13 + $miles_over_twenty * 1 + $tip;
			}
		} elseif (in_array($option_id, ["dss_XEWdAE"])) {
			if (ceil($distance) <= 8) {
				$price = 10 + $tip;
			} else {
				$milesOver8 = ceil($distance) - 8;
				$price = 10 + $milesOver8 * 1 + $tip;
			}
		} else {
			if (ceil($distance) <= 30) {
				$distance_price = 10 + ceil($distance) * 0.5 + $tip;
				$total_price = ceil($total_amount_cents + 3) + $tip;
				$price = $distance_price < $total_price ? $total_price : $distance_price;
			} else {
				$price = ceil($total_amount_cents * 1.15) + $tip;
			}
		}

		return number_format($price, 2, '.', '');
	}
}
