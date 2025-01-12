<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\Orders\Status as EStatus;
use App\Enums\Orders\DeliveryStyle as EDeliveryStyle;
return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('orders.orders', function (Blueprint $table) {
			$table->id();
			$table->string('job_id')->unique(); // Unique identifier for the jobs
			$table->string('email'); // Email associated with the order
			$table->enum('status', array_map(fn($case) => $case->value, EStatus::cases())); // Adjusted enum
			$table->timestamp('delivery_date'); // Delivery date with time precision
			$table->string('delivery_start_time')->nullable(); // Delivery start time (optional)
			$table->string('delivery_end_time')->nullable(); // Delivery end time (optional)
			$table->text('pickup_address'); // Pickup address
			$table->string('pickup_name'); // Name for pickup
			$table->text('dropoff_address'); // Dropoff address
			$table->string('dropoff_name'); // Name for dropoff
			$table->decimal('distance', 5, 2); // Distance in miles
			$table->decimal('price', 8, 2); //
			$table->decimal('tip', 8, 2); //
			$table->enum('delivery_style', array_map(fn($case) => $case->value, EDeliveryStyle::cases())); // Delivery style options
			$table->boolean('asap')->nullable()->default(false); // Delivery style options
			$table->boolean('stripe_processed')->default(false);
			$table->timestamps(); // Created at and updated at timestamps
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('orders.orders');
	}
};
