<?php

declare(strict_types=1);

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

final class AutoMatchCustomersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    protected IntegrationCustomer $integrationCustomer;

    public function __construct(IntegrationCustomer $integrationCustomer)
    {
        $this->integrationCustomer = $integrationCustomer;
    }

    public function handle(CustomerMappingService $mappingService): void
    {
        try {
            
            if ($this->integrationCustomer->customerMappings()->exists()) {
                Log::info('Integration customer already mapped', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                ]);
                return;
            }

            // Check if this integration is the Source of Truth
            if ($this->integrationCustomer->integration->is_source_of_truth) {
                Log::info('Processing Source of Truth customer creation', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'integration_type' => $this->integrationCustomer->integration->integration_type,
                ]);
                
                $mappingService->createFromSourceOfTruth(
                    $this->integrationCustomer, 
                    $this->integrationCustomer->integration->integration_type
                );
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

            // Use embedding-enhanced matching (Levenshtein first, then embeddings as fallback)
            $match = $mappingService->autoMatchWithEmbeddings($this->integrationCustomer);

            if ($match) {
                $requiresManualReview = $company->settings['customer_mapping']['manual_match_required'] ?? false;
                $mappingType = $match['mapping_type'];

                // Update IntegrationCustomer.customer_id (CRITICAL!)
                $this->integrationCustomer->customer_id = $match['customer']->customer_id;
                $this->integrationCustomer->save();

                // Determine final mapping type based on review requirement
                $finalMappingType = $requiresManualReview ? 'auto_pending' : $mappingType;
                $matchMethod = $mappingType === 'auto_embedding' ? 'embeddings' : 'Levenshtein';

                // Create or update mapping record for audit trail
                CustomerMapping::updateOrCreate(
                    ['integration_customer_id' => $this->integrationCustomer->integration_customer_id],
                    [
                        'customer_id' => $match['customer']->customer_id,
                        'mapping_type' => $finalMappingType,
                        'confidence_score' => $match['confidence_score'],
                        'mapped_at' => now(),
                        'notes' => "Auto-matched via {$matchMethod} with {$match['confidence_score']}% confidence",
                    ]
                );

                Log::info('Customer auto-matched', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'customer_id' => $match['customer']->customer_id,
                    'confidence_score' => $match['confidence_score'],
                    'mapping_type' => $finalMappingType,
                    'match_method' => $matchMethod,
                ]);
            } else {
                Log::info('No auto-match found, requires manual mapping', [
                    'integration_customer_id' => $this->integrationCustomer->integration_customer_id,
                    'customer_name' => $this->integrationCustomer->name,
                ]);    
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