<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use Illuminate\Support\Facades\Cache;

class CustomerMappingService
{
    public function autoMatch(IntegrationCustomer $integrationCustomer): ?array
    {
        $company = $integrationCustomer->integration->company;
        $threshold = $company->settings['customer_mapping']['auto_match_threshold'] ?? 85;

        // Cache the customer list for 60 seconds to avoid repeated DB queries during batch processing
        $existingCustomers = Cache::remember("customer_mapping_list_{$company->company_id}", 60, function () use ($company) {
            return Customer::where('company_id', $company->company_id)
                ->where('status', 'active')
                ->get();
        });

        $bestMatch = null;
        $bestScore = 0;

        foreach ($existingCustomers as $customer) {
            $score = $this->calculateSimilarity(
                $integrationCustomer->name,
                $customer->canonical_name
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $customer;
            }
        }

        if ($bestScore >= $threshold) {
            return [
                'customer' => $bestMatch,
                'confidence_score' => $bestScore,
                'mapping_type' => 'auto',
            ];
        }

        return null;
    }

    public function calculateSimilarity(string $name1, string $name2): int
    {
        $original1 = $name1;
        $original2 = $name2;

        $name1 = $this->normalizeName($name1);
        $name2 = $this->normalizeName($name2);

        if ($name1 === $name2) {
            return 100;
        }

        if (strtolower($name1) === strtolower($name2)) {
            return 95;
        }

        $core1 = $this->extractCoreName($original1);
        $core2 = $this->extractCoreName($original2);

        if (strtolower($core1) === strtolower($core2)) {
            return 90;
        }

        $maxLength = max(strlen($name1), strlen($name2));
        $distance = levenshtein(strtolower($name1), strtolower($name2));
        $similarity = (1 - ($distance / $maxLength)) * 100;

        $coreMaxLength = max(strlen($core1), strlen($core2));
        $coreDistance = levenshtein(strtolower($core1), strtolower($core2));
        $coreSimilarity = (1 - ($coreDistance / $coreMaxLength)) * 100;

        $similarity = max($similarity, $coreSimilarity);

        if ($this->hasCommonAbbreviations($original1, $original2)) {
            $similarity = min(100, $similarity + 20);
        }

        if ($coreSimilarity >= 70) {
            $similarity = min(100, $similarity + 15);
        }

        return (int) round($similarity);
    }

    private function extractCoreName(string $name): string
    {
        $suffixes = [
            'Pty Ltd',
            'Pty. Ltd.',
            'Pty Ltd.',
            'Limited',
            'Ltd',
            'Ltd.',
            'Incorporated',
            'Inc',
            'Inc.',
            'Corporation',
            'Corp',
            'Corp.',
            'Company',
            'Co',
            'Co.',
            'Proprietary',
            'Technologies',
            'Technology',
            'Tech',
            'Solutions',
            'Services',
            'Group',
            'International',
            'Australia',
            'Australian',
        ];

        $core = $name;
        foreach ($suffixes as $suffix) {
            $pattern = '/\s+' . preg_quote($suffix, '/') . '$/i';
            $core = preg_replace($pattern, '', $core);
        }

        return trim($core);
    }

    public function createManualMapping(
        IntegrationCustomer $integrationCustomer,
        Customer $customer,
        int $userId,
        ?string $notes = null
    ): CustomerMapping {
        $confidenceScore = $this->calculateSimilarity(
            $integrationCustomer->name,
            $customer->canonical_name
        );

        return CustomerMapping::create([
            'customer_id' => $customer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'mapping_type' => 'manual',
            'confidence_score' => $confidenceScore,
            'mapped_by_user_id' => $userId,
            'mapped_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function getSuggestedMatches(IntegrationCustomer $integrationCustomer, int $limit = 5): array
    {
        $company = $integrationCustomer->integration->company;

        $existingCustomers = Customer::where('company_id', $company->company_id)
            ->where('status', 'active')
            ->get();

        $suggestions = [];

        foreach ($existingCustomers as $customer) {
            $score = $this->calculateSimilarity(
                $integrationCustomer->name,
                $customer->canonical_name
            );

            if ($score > 30) {
                $suggestions[] = [
                    'customer' => $customer,
                    'confidence_score' => $score,
                ];
            }
        }

        usort($suggestions, fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Create a unified customer from a source of truth integration customer
     * This centralizes the logic previously duplicated in integration classes
     */
    public function createFromSourceOfTruth(IntegrationCustomer $integrationCustomer, string $integrationType): array
    {
        // Check for existing customer with the same name to prevent duplicates
        $existingCustomer = Customer::where('company_id', $integrationCustomer->integration->company_id)
            ->whereRaw('LOWER(canonical_name) = ?', [strtolower($integrationCustomer->name)])
            ->first();

        if ($existingCustomer) {
            $unifiedCustomer = $existingCustomer;
            $message = 'Auto-linked to existing customer from source of truth';

            \Log::info('Source of Truth: Linked to existing customer', [
                'integration_type' => $integrationType,
                'customer_name' => $integrationCustomer->name,
                'existing_customer_id' => $unifiedCustomer->customer_id,
            ]);
        } else {
            // Create unified customer
            $unifiedCustomer = Customer::create([
                'company_id' => $integrationCustomer->integration->company_id,
                'canonical_name' => $integrationCustomer->name,
                'status' => 'active',
                'metadata' => [],
            ]);
            $message = 'Auto-created from source of truth integration';

            \Log::info('Source of Truth: Created new unified customer', [
                'integration_type' => $integrationType,
                'customer_name' => $integrationCustomer->name,
                'new_customer_id' => $unifiedCustomer->customer_id,
            ]);
        }

        // Link integration customer to the unified customer
        $integrationCustomer->customer_id = $unifiedCustomer->customer_id;
        $integrationCustomer->save();

        // Create mapping record
        CustomerMapping::create([
            'customer_id' => $unifiedCustomer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'mapping_type' => 'auto',
            'confidence_score' => 100,
            'mapped_at' => now(),
            'notes' => $message,
        ]);

        return [
            'external_id' => $integrationCustomer->external_id,
            'name' => $integrationCustomer->name,
            'unified_customer_id' => $unifiedCustomer->customer_id,
            'canonical_name' => $unifiedCustomer->canonical_name,
        ];
    }

    public function mergeCustomers(Customer $primary, Customer $duplicate, int $userId): Customer
    {
        CustomerMapping::where('customer_id', $duplicate->customer_id)
            ->update(['customer_id' => $primary->customer_id]);

        $duplicate->reportSchedules()->update(['customer_id' => $primary->customer_id]);

        $duplicate->generatedReports()->update(['customer_id' => $primary->customer_id]);

        $duplicate->status = 'inactive';
        $duplicate->save();

        return $primary;
    }

    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }

    private function hasCommonAbbreviations(string $name1, string $name2): bool
    {
        $abbreviations = [
            'Corporation' => 'Corp',
            'Limited' => 'Ltd',
            'Incorporated' => 'Inc',
            'Company' => 'Co',
            'Proprietary' => 'Pty',
        ];

        foreach ($abbreviations as $full => $abbr) {
            if (
                (stripos($name1, $full) !== false && stripos($name2, $abbr) !== false) ||
                (stripos($name2, $full) !== false && stripos($name1, $abbr) !== false)
            ) {
                return true;
            }
        }

        return false;
    }
}
