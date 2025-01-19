<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;


class fillDatabaseJob extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'script:fill-database';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Import orders from google sheets into the database';
	
	/**
	 * Execute the console command.
	 *
	 * @return int
	 */  
	public function handle()
	{
		$spreadsheet_id = env('GOOGLE_SPREADSHEET_ID');
		$range = env('GOOGLE_SHEET_RANGE');

		$client = new GoogleClient();
		$client->setApplicationName(env('GOOGLE_APP_NAME'));
		$client->setScopes([GoogleSheets::SPREADSHEETS_READONLY]);
		$client->setDeveloperKey(env('GOOGLE_API_KEY'));
		$service = new GoogleSheets($client);
		
		try {
			$response = $service->spreadsheets_values->get($spreadsheet_id, $range);
			$values = $response->getValues();
		} catch (\Exception $e) {
			$this->error("Error fetching data: {$e->getMessage()}");
			return;
		}

		if (empty($values)) {
			$this->info('No data found in the Google Sheet.');
			return;
		}

		foreach ($values as $index => $row) {
			if ($index === 0) continue;
			try {
				DB::table('orders.orders')->insert([
					'job_id' => $row[0],
					'email' => $row[1],
					'status' => $row[2],
					'delivery_date' => Carbon::parse($row[3]),
					'asap' => strtolower($row[4]) === 'asap',
					'pickup_address' => $row[6],
					'pickup_name' => $row[7],
					'dropoff_address' => $row[8],
					'dropoff_name' => $row[9],
					'distance' => (float) $row[10],
					'price' => (float) $row[11],
					'tip' => 0.00,
					'delivery_style' => $row[12],
					'stripe_processed' => false,
					'created_at' => now(),
					'updated_at' => now(),
				]);
				$this->info("Inserted Job ID: {$row[0]}");
			} catch (\Exception $e) {
				Log::error("Failed to insert Job ID: {$row[0]}, Error: {$e->getMessage()}");
				$this->error("Failed to insert Job ID: {$row[0]}");
			}
		}

		$this->info('Database fill from Google Sheets completed.');
	}
}
