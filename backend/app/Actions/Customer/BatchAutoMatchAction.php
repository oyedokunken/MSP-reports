<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use App\Services\CustomerMappingService;
use Illuminate\Support\Facades\Log;

final class BatchAutoMatchAction
{
    use LogsAudit;

    private CustomerMappingService $mappingService;

    public function __construct(CustomerMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * @param array<int> $integrationCustomerIds
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $integrationCustomerIds = [], array $options = []): array
    {
        $autoApply = $options['auto_apply'] ?? false;
        $minConfidence = $options['min_confidence'] ?? 80;

        // Get unmapped customers
        $query = IntegrationCustomer::whereHas('integration', function ($q) use ($company) {
            $q->where('company_id', $company->company_id);
        })->whereNull('customer_id');

        if (!empty($integrationCustomerIds)) {
            $query->whereIn('integration_customer_id', $integrationCustomerIds);
        }

        $integrationCustomers = $query->with('integration')->limit(100)->get();

        if ($integrationCustomers->isEmpty()) {
            return [
                'processed' => 0,
                'matched' => 0,
                'applied' => 0,
                'results' => [],
            ];
        }

        // Batch process with embeddings
        $matches = $this->mappingService->batchAutoMatchWithEmbeddings(
            $integrationCustomers->all(),
            $company->company_id
        );

        $results = [];
        $appliedCount = 0;

        foreach ($integrationCustomers as $ic) {
            $match = $matches[$ic->integration_customer_id] ?? null;

            $result = [
                'integration_customer_id' => $ic->integration_customer_id,
                'name' => $ic->name,
                'match_found' => $match !== null,
                'applied' => false,
                'suggestion' => null,
            ];

            if ($match) {
                $result['suggestion'] = [
                    'customer_id' => $match['customer']->customer_id,
                    'canonical_name' => $match['customer']->canonical_name,
                    'confidence_score' => $match['confidence_score'],
                    'mapping_type' => $match['mapping_type'],
                ];

                // Auto-apply if requested and confidence is high enough
                if ($autoApply && $match['confidence_score'] >= $minConfidence) {
                    try {
                        $ic->customer_id = $match['customer']->customer_id;
                        $ic->save();

                        CustomerMapping::create([
                            'customer_id' => $match['customer']->customer_id,
                            'integration_customer_id' => $ic->integration_customer_id,
                            'mapping_type' => $match['mapping_type'],
                            'confidence_score' => $match['confidence_score'],
                            'mapped_at' => now(),
                            'notes' => 'Auto-matched via batch embedding process',
                        ]);

                        $result['applied'] = true;
                        $appliedCount++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to auto-apply match', [
                            'integration_customer_id' => $ic->integration_customer_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $results[] = $result;
        }

        $summary = [
            'processed' => count($results),
            'matched' => count(array_filter($results, fn($r) => $r['match_found'])),
            'applied' => $appliedCount,
            'results' => $results,
        ];

        $this->audit($company->company_id, 'customer_batch_auto_matched', null, [
            'processed' => $summary['processed'],
            'matched' => $summary['matched'],
            'applied' => $summary['applied'],
        ]);

        return $summary;
    }
}
