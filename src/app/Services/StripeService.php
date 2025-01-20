<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeService {

	/**
	 * @property \Stripe\StripeClient|null $client
	 */
	private $client = null;


	//protected method
	protected function getClient(): StripeClient
	{
		if ($this->client === null) {
			$this->client = new StripeClient(env('STRIPE_SECRET_KEY'));
		}

		//return
		return $this->client;
	}

	//final public methods
	/**
	 * Process a job and update the Stripe invoice.
	 *
	 * @param array $job_details
	 * The job details.
	 * 
	 * @return void
	 */
	final public function processStripe(array $job_details): void
	{
		//process stripe
		Log::info('Processing Stripe', $job_details);

		$existingDrafts = self::getDrafts();
		Log::info('Total Drafts Found: ' . count($existingDrafts));

		//get email
		$customerEmail = $job_details['email'];
		$deliveryDate = new \DateTime($job_details['delivery_date']);
		$month = $deliveryDate->format('m');
		$year = $deliveryDate->format('Y');

		$matchingDrafts = array_filter($existingDrafts, function ($draft) use ($customerEmail, $month, $year) {
		   return $draft['customer_email'] === $customerEmail &&
				  $draft['metadata']['month'] === $month &&
				  $draft['metadata']['year'] === $year;
	   });

	   Log::info('Matching Drafts Found: ' . count($matchingDrafts));

	   $itemDetails = $this->formatItemDetails($job_details);

	   //add draft
	   if (!empty($matchingDrafts)) {
		   $existingDraftId = $matchingDrafts[0]['id'];
		   $this->addItemToExistingDraft($existingDraftId, $itemDetails);
	   } else {
		   $newDraftId = $this->createNewDraft($itemDetails);
		   $this->addItemToExistingDraft($newDraftId, $itemDetails);
	   }
   }
   
   final public function postProcessUpdate(array $job_details, ?Order $order = null): void
   {
		$price_value = null;

		$parterns = [
			"dss_bN9XiB","dsr_cv2WbL","dss_d6tSpe",
			"dss_7jSMmA","opn_836HQA","dss_65ontq","dss_PsCM3y"
		];

		if ($order !== null) {

			if ($job_details['status'] === "Created" && $order['status'] === "Canceled Driver") {
				Log::info("Job was canceled");
				return;
			}
			if (in_array($order->status, ["Assigned Driver", "Pickup Enroute", "Pickup Arrived"], true) && $job_details['status'] === 'Canceled') {
				if (in_array($order['option_id'], $parterns, true)) {
					$price_value = "15.00";
					
				} else {
					$price_value = "10.00";
				}

			} else if (in_array($order->status, ["Dropoff Enroute", "Dropoff Arrived", "Pickup Complete", "RETURN_IN_PROGRESS", "RETURNED"], true) && $job_details['status'] === 'Canceled'){
				$job_details['status'] = "Other";

			} else if ($job_details['status'] === 'Canceled') {
				$price_value = "0.00";
			}

			//update
			$update = [
				'email' => $job_details['email'],
				'status' => $job_details['status'],
				'distance' => $job_details['distance'],
				'delivery_date' => $job_details['delivery_date'],
				'delivery_start_time' => $job_details['delivery_start_time'],
				'delivery_end_time' => $job_details['delivery_end_time'],
				'pickup_address' => $job_details['pickup_address'],
				'pickup_name' => $job_details['pickup_name'],
				'asap' => $job_details['asap'],
				'dropoff_address' => $job_details['dropoff_address'],
				'dropoff_name' => $job_details['dropoff_name']
			];

			if ($price_value !== null) {
				$update['price'] = $price_value;
			}

			$order->update($update);

		} else {

			if ($job_details['status'] === "Canceled") {
				$job_details['price'] = "0.00";
			}

			//create
			Order::create([
				'job_id' => $job_details['job_id'],
				'email' => $job_details['email'],
				'status' => $job_details['status'],
				'distance' => $job_details['distance'],
				'price' => $job_details['price'],
				'tip' => $job_details['tip'],
				'delivery_date' => $job_details['delivery_date'],
				'delivery_start_time' => $job_details['delivery_start_time'],
				'delivery_end_time' => $job_details['delivery_end_time'],
				'pickup_address' => $job_details['pickup_address'],
				'pickup_name' => $job_details['pickup_name'],
				'asap' => $job_details['asap'],
				'dropoff_address' => $job_details['dropoff_address'],
				'dropoff_name' => $job_details['dropoff_name'],
				'delivery_style' => $job_details['delivery_style'],
				'stripe_processed' => false
			]);
		}
   }

    /**
	 * Get all draft invoices.
	 * 
	 * @return array
	 * The draft invoices.
	 */
	final public function getDrafts(): array
	{
		//initialize
		$client = self::getClient();
		$drafts = [];

		//get drafts
		do {
			$params = [
				'limit' => 100,
				'status' => 'draft',
				'starting_after' => $drafts ? end($drafts)['id'] : null,
			];

			if ($params['starting_after'] === null){
				unset($params['starting_after']);
			}

			$items = $client->invoices->all($params);

			$drafts = array_merge($drafts, $items['data']);

			$has_more = $items['has_more'] ?? false;
		} while ($has_more);

		//log drafts
		foreach ($drafts as $invoice) {
			if (!empty($invoice['customer_email'])) {
				Log::info('Customer Email: ' . $invoice['customer_email']);
			}
		}

		//return
		return $drafts;
	}


	/**
	 * Get or create a customer ID based on email.
	 *
	 * @param string $email
	 * The email of the customer.
	 * 
	 * @param string $name
	 * The name of the customer.
	 * 
	 * @throws \Exception
	 * 
	 * @return string|null
	 * The customer ID.
	 */
	final public function getCustomerId(string $email, string $name): ?string
	{
		$client = $this->getClient();

		//normalize email
		$email = trim(strtolower($email));

		try {
			//get customers
			$customers = $client->customers->all([
				'limit' => 100,
				'email' => $email,
			]);

			if (!empty($customers->data)) {
				return $customers->data[0]->id;
			}

			Log::info("Customer doesn't exist, creating a new one.");
			//create new customer
			$new_customer = $this->createCustomer($email, $name, $name);
			return $new_customer->id;

		} catch (\Exception $e) {
			Log::error('Error fetching or creating customer: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Create a new draft invoice.
	 *
	 * @param array $item_details
	 * The item details.
	 * 
	 * @throws \Exception
	 * 
	 * @return string
	 * The draft ID.
	 */
	final public function createNewDraft(array $item_details): string
	{
		$client = $this->getClient();
		
		try {
			$payload = [
				'customer' => $item_details['customer_id'],
				'currency' => 'USD',
				'metadata' => [
					'month' => date('m', strtotime($item_details['delivery_date'])),
					'year' => date('Y', strtotime($item_details['delivery_date'])),
				],
			];

			$invoice = $client->invoices->create($payload);

			return $invoice->id;
		} catch (\Exception $e) {
			Log::error('Failed to create draft invoice: ' . $e->getMessage());
			throw $e;
		}
	}


	/**
	 * Format the item details.
	 *
	 * @param array $job_details
	 * The job details.
	 * 
	 * @return array
	 * The formatted item details.
	 */
	final public function formatItemDetails(array $job_details): array
	{
		$email = strtolower(trim($job_details['email']));
		$price = (float)str_replace('$', '', $job_details['price']);

		//calculate tip
		$tip = $job_details['tip'] ?? 0;

		//format date
		$delivery_date = new \DateTime($job_details['delivery_date']);
		$month = $delivery_date->format('d');
		$year = $delivery_date->format('m');

		$item_name = sprintf('%s/%s - %.2f Miles', $year, $month, $job_details['distance']);

		if ($tip) {
			$item_name .= sprintf(' - $%.2f Add.', $tip);
		}

		if ($job_details['status'] === 'Canceled') {
			$item_name .= ' - Canceled Driver';
			if ($tip) {
				$item_name = str_replace(sprintf(' - $%.2f Add.', $tip), '', $item_name);
			}
		}

		$qty = 1;
		$customer_id = $this->getCustomerId($email, $job_details['pickup_name']);
		
		//return
		return [
			'customer_id' => $customer_id,
			'customer_email' => $email,
			'item_name' => $item_name,
			'quantity' => $qty,
			'price' => $price,
			'job_id' => $job_details['job_id'],
			'delivery_date' => $job_details['delivery_date'],
		];
	}


	/**
	 * Add an item to an existing draft invoice.
	 *
	 * @param string $existing_draft_id
	 * The existing draft ID.
	 * 
	 * @param array $item_details
	 * The item details.
	 * 
	 * @return void
	 */
	final public function addItemToExistingDraft(string $existing_draft_id, array $item_details): void
	{
		//client
		$client = $this->getClient();

		try {
			//get drafts
			$draftItem = $this->getDraftItems($existing_draft_id, $item_details['job_id']);

			if ($draftItem !== null) {
				if ($item_details['price'] == 0) {
					$client->invoiceItems->delete($draftItem['id']);
				} else {
					$client->invoiceItems->update($draftItem['id'], [
						'amount' => $item_details['price'] * 100,
						'description' => $item_details['item_name'],
					]);
				}
			} else if ($item_details['price'] != 0) {
				$client->invoiceItems->create([
					'customer' => $item_details['customer_id'],
					'description' => $item_details['item_name'],
					'amount' => $item_details['price'] * 100,
					'invoice' => $existing_draft_id,
					'metadata' => [
						'job_id' => $item_details['job_id'],
					],
				]);
				Log::info('Draft Updated Successfully', ['item' => $item_details]);
				return;
			} else {
				Log::info('Ignored 0 price.', ['item' => $item_details]);
				return;
			}
		} catch (\Exception $e) {
			Log::error('Failed to update draft: ' . $e->getMessage());
			throw $e;
		}

		return;
	}

	/**
	 * Create a new customer.
	 *
	 * @param string $email
	 * The email of the customer.
	 * 
	 * @param string $name
	 * The name of the customer.
	 * 
	 * @param string $description
	 * The description of the customer.
	 * 
	 * @throws \Exception
	 * 
	 * @return \Stripe\Customer
	 * The customer object.
	 */
	private function createCustomer(string $email, string $name, string $description): Customer
	{
		//client
		$client = $this->getClient();

		try {
			//create customer
			$customer = $client->customers->create([
				'email' => $email,
				'name' => $name,
				'description' => $description,
			]);

			Log::info("Customer created successfully: " . $customer->id);
			return $customer;
		} catch (\Exception $e) {
			Log::error("Error creating customer: " . $e->getMessage());
			throw $e;
		}
	}

	   /**
	 * Fetch draft items for a specific job ID.
	 *
	 * @param string $draft_id
	 * The draft ID.
	 * 
	 * @param string $job_id
	 * The job ID.
	 * 
	 * @return \Stripe\InvoiceItem|null
	 * The draft item.
	 */
	private function getDraftItems(string $draft_id, string $job_id): ?\Stripe\InvoiceItem
	{
		//client
		$client = $this->getClient();

		try {
			$items = $client->invoiceItems->all([
				'invoice' => $draft_id,
				'limit' => 100,
			]);

			//fetch jobs
			foreach ($items->data as $item) {
				if (!empty($item->metadata['job_id']) && $item->metadata['job_id'] === $job_id) {
					return $item;
				}
			}
		} catch (\Exception $e) {
			Log::error('Failed to fetch draft items: ' . $e->getMessage());
		}

		return null;
	}
}
