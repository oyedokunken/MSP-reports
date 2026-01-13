<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Services\CustomerMappingService;
use Illuminate\Support\Facades\Log;

class BatchAutoMatchCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600; // 10 minutes for batch processing

    protected Integration $integration;

    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
    }

    public function handle(CustomerMappingService $mappingService): void
    {
        try {
            Log::info('Starting batch auto-match for integration', [
                'integration_id' => $this->integration->integration_id,
                'integration_type' => $this->integration->integration_type,
            ]);

            // CRITICAL: Clear the customer cache to ensure fresh data on first sync
            // Without this, the first sync may use stale cache and fail to match
            $companyId = $this->integration->company_id;
            \Illuminate\Support\Facades\Cache::forget("customer_mapping_list_{$companyId}");
            Log::debug('Cleared customer mapping cache', ['company_id' => $companyId]);

            // Get unmapped customers, but only those that are NEW or UPDATED since last sync
            // This avoids wasteful re-processing of the same unchanged customers every sync
            $query = IntegrationCustomer::where('integration_id', $this->integration->integration_id)
                ->whereNull('customer_id');

            // Process ALL unmapped customers to retry matching
            // Previous optimization (skipping old unmapped) prevented retries when master list updated
            // if ($this->integration->last_sync_at && !$this->integration->is_source_of_truth) { ... }

            $unmappedCustomers = $query->get();

            $totalCustomers = $unmappedCustomers->count();
            $matchedCount = 0;
            $createdCount = 0;
            $failedCount = 0;

            Log::info('Batch auto-match: processing customers', [
                'integration_id' => $this->integration->integration_id,
                'total_unmapped' => $totalCustomers,
                'filter' => 'all unmapped',
            ]);

            // Process in chunks to avoid memory issues
            $unmappedCustomers->chunk(50)->each(function ($chunk) use ($mappingService, &$matchedCount, &$createdCount, &$failedCount) {
                foreach ($chunk as $integrationCustomer) {
                    try {
                        // Skip if already mapped (race condition check)
                        if ($integrationCustomer->customer_id) {
                            continue;
                        }

                        // Check if this integration is the Source of Truth
                        if ($this->integration->is_source_of_truth) {
                            $mappingService->createFromSourceOfTruth(
                                $integrationCustomer,
                                $this->integration->integration_type
                            );
                            $createdCount++;
                            continue;
                        }

                        $company = $this->integration->company;
                        $autoMatchEnabled = $company->settings['customer_mapping']['auto_match_enabled'] ?? true;

                        if (!$autoMatchEnabled) {
                            continue;
                        }

                        // Attempt auto-match
                        $match = $mappingService->autoMatch($integrationCustomer);

                        if ($match) {
                            $requiresManualReview = $company->settings['customer_mapping']['manual_match_required'] ?? false;

                            // Update IntegrationCustomer.customer_id
                            $integrationCustomer->customer_id = $match['customer']->customer_id;
                            $integrationCustomer->save();

                            // Create mapping record for audit trail
                            \App\Models\CustomerMapping::updateOrCreate(
                                ['integration_customer_id' => $integrationCustomer->integration_customer_id],
                                [
                                    'customer_id' => $match['customer']->customer_id,
                                    'mapping_type' => $requiresManualReview ? 'auto_pending' : 'auto',
                                    'confidence_score' => $match['confidence_score'],
                                    'mapped_at' => now(),
                                    'notes' => "Auto-matched with {$match['confidence_score']}% confidence (batch)",
                                ]
                            );

                            $matchedCount++;
                        }

                    } catch (\Exception $e) {
                        $failedCount++;
                        Log::error('Batch auto-match: failed to process customer', [
                            'integration_customer_id' => $integrationCustomer->integration_customer_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            Log::info('Batch auto-match completed', [
                'integration_id' => $this->integration->integration_id,
                'total_processed' => $totalCustomers,
                'matched' => $matchedCount,
                'created' => $createdCount,
                'failed' => $failedCount,
                'still_unmapped' => $totalCustomers - $matchedCount - $createdCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Batch auto-match job failed', [
                'integration_id' => $this->integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
