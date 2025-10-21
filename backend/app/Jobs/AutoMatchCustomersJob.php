<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\IntegrationCustomer;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Services\CustomerMappingService;
use Illuminate\Support\Facades\Log;

class AutoMatchCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300;

    protected IntegrationCustomer $integrationCustomer;

    /**
     * Create a new job instance.
     */
    public function __construct(IntegrationCustomer $integrationCustomer)
    {
        $this->integrationCustomer = $integrationCustomer;
    }

    /**
     * Execute the job.
     */
    public function handle(CustomerMappingService $mappingService): void
    {
        try {
            // Check if already mapped
            if ($this->integrationCustomer->customerMappings()->exists()) {
                Log::info('Integration customer already mapped', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                ]);
                return;
            }

            $company = $this->integrationCustomer->integration->company;
            $autoMatchEnabled = $company->settings['customer_mapping']['auto_match_enabled'] ?? true;

            if (!$autoMatchEnabled) {
                Log::info('Auto-matching disabled for company', [
                    'company_id' => $company->company_id,
                ]);
                return;
            }

            // Attempt auto-match
            $match = $mappingService->autoMatch($this->integrationCustomer);

            if ($match) {
                $requiresManualReview = $company->settings['customer_mapping']['manual_match_required'] ?? false;

                // Create mapping
                CustomerMapping::create([
                    'customer_id' => $match['customer']->customer_id,
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'mapping_type' => $requiresManualReview ? 'auto_pending' : 'auto',
                    'confidence_score' => $match['confidence_score'],
                    'mapped_at' => now(),
                    'notes' => "Auto-matched with {$match['confidence_score']}% confidence",
                ]);

                Log::info('Customer auto-matched', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'customer_id' => $match['customer']->customer_id,
                    'confidence_score' => $match['confidence_score'],
                ]);
            } else {
                Log::info('No auto-match found, requires manual mapping', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'customer_name' => $this->integrationCustomer->customer_name,
                ]);

                // TODO: Notify admins of unmapped customer
            }

        } catch (\Exception $e) {
            Log::error('Auto-match customer job failed', [
                'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
