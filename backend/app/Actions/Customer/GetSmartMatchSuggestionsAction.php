<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Company;
use App\Models\IntegrationCustomer;
use App\Services\CustomerMappingService;

final class GetSmartMatchSuggestionsAction
{
    private CustomerMappingService $mappingService;

    public function __construct(CustomerMappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    /**
     * @param array<int> $integrationCustomerIds
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $integrationCustomerIds, bool $useAgent = false): array
    {
        // Get the integration customers
        $integrationCustomers = IntegrationCustomer::whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->whereNull('customer_id')
            ->with('integration')
            ->get();

        if ($integrationCustomers->isEmpty()) {
            return [
                'results' => [],
                'message' => 'No unmapped customers found for the given IDs.',
            ];
        }

        $results = [];

        foreach ($integrationCustomers as $ic) {
            // Try embedding-based matching (or agent if requested)
            if ($useAgent) {
                $match = $this->mappingService->autoMatchWithAgent($ic);
            } else {
                $match = $this->mappingService->autoMatchWithEmbeddings($ic, true);
            }

            $result = [
                'integration_customer_id' => $ic->integration_customer_id,
                'name' => $ic->name,
                'integration_type' => $ic->integration->integration_type ?? 'Unknown',
                'match_found' => $match !== null,
                'suggestion' => null,
            ];

            if ($match) {
                $result['suggestion'] = [
                    'customer_id' => $match['customer']->customer_id,
                    'canonical_name' => $match['customer']->canonical_name,
                    'confidence_score' => $match['confidence_score'],
                    'mapping_type' => $match['mapping_type'],
                    'reasoning' => $match['reasoning'] ?? null,
                ];
            }

            $results[] = $result;
        }

        return [
            'results' => $results,
            'meta' => [
                'total_requested' => count($integrationCustomerIds),
                'total_processed' => count($results),
                'matches_found' => count(array_filter($results, fn($r) => $r['match_found'])),
                'method' => $useAgent ? 'agent' : 'embedding',
            ],
        ];
    }
}
