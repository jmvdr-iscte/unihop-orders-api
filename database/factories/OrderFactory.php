<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Enums\Orders\Status as EStatus;
use App\Enums\Orders\DeliveryStyle as EDeliveryStyle;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class; 

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => 'job_' . Str::random(22),
            'email' => $this->faker->unique()->safeEmail(),
            'status' => $this->faker->randomElement(array_map(fn($case) => $case->value, EStatus::cases())),
            'delivery_date' => $this->faker->dateTimeBetween('+1 days', '+1 month'),
            'delivery_start_time' => $this->faker->time(),
            'delivery_end_time' => $this->faker->time(),
            'pickup_address' => $this->faker->address(),
            'pickup_name' => $this->faker->name(),
            'dropoff_address' => $this->faker->address(),
            'dropoff_name' => $this->faker->name(),
            'distance' => $this->faker->randomFloat(2, 1, 149), // Random distance between 1 and 100 miles
            'standard_delivery_tip' => $this->faker->randomFloat(2, 0, 50), // Random tip between 0 and 50
            'delivery_style' => $this->faker->randomElement(array_map(fn($case) => $case->value, EDeliveryStyle::cases())),
            'asap' => $this->faker->boolean(),
        ];
    }
}
