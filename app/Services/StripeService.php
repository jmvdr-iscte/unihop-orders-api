<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeService {

    /**
     * @property \Stripe\StripeClient|null $client
     */
    private $client = null;



    protected function getClient(): StripeClient
    {
        if ($this->client === null) {
            $this->client = new StripeClient(env('STRIPE_SECRET_KEY'));
        }
        return $this->client;
    }


   public function processStripe(array $job_details, string $stripe_type, ?string $updated_price = null): void
   {
       Log::info('Processing Stripe', $job_details);

       $existingDrafts = self::getDrafts();
       Log::info('Total Drafts Found: ' . count($existingDrafts));

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

       $itemDetails = $this->formatItemDetails($job_details, $stripe_type, $updated_price);

       if (!empty($matchingDrafts)) {
           $existingDraftId = $matchingDrafts[0]['id'];
           $this->addItemToExistingDraft($existingDraftId, $itemDetails);
       } else {
           $newDraftId = $this->createNewDraft($itemDetails);
           $this->addItemToExistingDraft($newDraftId, $itemDetails);
       }
   }
   


    final public function getDrafts(): array
    {
        // init
        $client = (new self())->getClient();
        $drafts = [];

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

        foreach ($drafts as $invoice) {
            if (!empty($invoice['customer_email'])) {
                Log::info('Customer Email: ' . $invoice['customer_email']);
            }
        }

        return $drafts;
    }


    /**
     * Get or create a customer ID based on email.
     *
     * @param string $customerEmail
     * @param string $name
     * @return string|null
     */
    public function getCustomerId(string $email, string $name): ?string
    {
        $client = $this->getClient();

        // Normalize email
        $email = trim(strtolower($email));

        try {
            // Search for existing customer
            $customers = $client->customers->all([
                'limit' => 100,
                'email' => $email,
            ]);

            if (!empty($customers->data)) {
                return $customers->data[0]->id;
            }

            Log::info("Customer doesn't exist, creating a new one.");
            $new_customer = $this->createCustomer($email, $name, $name);
            return $new_customer->id;

        } catch (\Exception $e) {
            Log::error('Error fetching or creating customer: ' . $e->getMessage());
            throw $e;
        }
    }



    public function createNewDraft(array $item_details): string
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



    public function formatItemDetails(array $job_details, string $stripe_type, ?string $updated_price = null): array
    {
        $email = strtolower(trim($job_details['email']));
        $price = (float)str_replace('$', '', $job_details['standard_delivery_tip']);

        // Calculate tip
        $tip = $job_details['delivery_style'] ?? 0;

        // Format date
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

        if ($stripe_type === 'Canceled Driver' && $updated_price) {
            $price = (float)str_replace('$', '', $updated_price);
        }

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
     * @param string $exiting_draft_id
     * @param array $item_details
     * @return string
     */
    //REMOVE THE RETURNS
    public function addItemToExistingDraft(string $exiting_draft_id, array $item_details): string
    {
        $client = $this->getClient();

        try {
            $draftItem = $this->getDraftItems($exiting_draft_id, $item_details['job_id']);

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
                    'invoice' => $exiting_draft_id,
                    'metadata' => [
                        'job_id' => $item_details['job_id'],
                    ],
                ]);

                return 'Draft Updated Successfully';
            } else {
                return 'Ignored 0 price item';
            }
        } catch (\Exception $e) {
            Log::error('Failed to update draft: ' . $e->getMessage());
            throw $e;
        }

        return 'Draft Updated Successfully';
    }



    private function createCustomer(string $email, string $name, string $description): Customer
    {
        $client = $this->getClient();

        try {
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
     * @param string $job_id
     * @return array|null
     */
    private function getDraftItems(string $draft_id, string $job_id): ?array
    {
        $client = $this->getClient();

        try {
            $items = $client->invoiceItems->all([
                'invoice' => $draft_id,
                'limit' => 100,
            ]);

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
