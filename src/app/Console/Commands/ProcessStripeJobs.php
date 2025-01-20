<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StripeService;
use App\Models\Order;
use Illuminate\Support\Carbon;
use App\Enums\Orders\Status as EStatus;

class ProcessStripeJobs extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'stripe:process-jobs';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Process Stripe for all jobs within the last 7 days';

	/**
	 * Execute the console command.
	 * 
	 * @return void
	 */
	public function handle()
	{
		//initalize
		$this->info('Fetching jobs from the last 7 days...');
		
		$stripe_service = new StripeService();

		//get orders
		$orders = Order::where('created_at', '<=', Carbon::now()->subDays(8))
			->where('stripe_processed' , '=', false)
			->whereIn('status', ['Delivered', 'Canceled', 'Canceled Driver', 'Other'])
			->get();

		foreach ($orders as $order) {
			try {
				$this->info("Processing job ID: {$order->id}");
				
				$order_details = $order->toArray();

				$status = EStatus::tryFrom($order_details['status']);

				if ($status === null){
					$this->error("Failed to process job ID: {$order->id} - Error: Invalid Status");
					continue;
				}

				//process stripe
				$stripe_service->processStripe($order_details);
				
				$order->update([
					'stripe_processed' => true
				]);

				$this->info("Successfully processed job ID: {$order->id}");
			} catch (\Exception $e) {
				$this->error("Failed to process job ID: {$order->id} - Error: " . $e->getMessage());
			}
		}

		$this->info('Stripe processing completed.');
	}
}
