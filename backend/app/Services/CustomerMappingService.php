<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\Company;

class CustomerMappingService
{
    /**
     * Auto-match an integration customer to existing customers.
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @return array|null Returns mapping data if match found, null otherwise
     */
    public function autoMatch(IntegrationCustomer $integrationCustomer): ?array
    {
        $company = $integrationCustomer->integration->company;
        $threshold = $company->settings['customer_mapping']['auto_match_threshold'] ?? 85;
        
        // Get all existing customers for this company
        $existingCustomers = Customer::where('company_id', $company->company_id)
            ->where('status', 'active')
            ->get();
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($existingCustomers as $customer) {
            $score = $this->calculateSimilarity(
                $integrationCustomer->customer_name,
                $customer->canonical_name
            );
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $customer;
            }
        }
        
        // If best match exceeds threshold, return it
        if ($bestScore >= $threshold) {
            return [
                'customer' => $bestMatch,
                'confidence_score' => $bestScore,
                'mapping_type' => 'auto',
            ];
        }
        
        return null;
    }

    /**
     * Calculate similarity score between two customer names.
     * 
     * @param string $name1
     * @param string $name2
     * @return int Score from 0-100
     */
    public function calculateSimilarity(string $name1, string $name2): int
    {
        // Normalize names
        $name1 = $this->normalizeName($name1);
        $name2 = $this->normalizeName($name2);
        
        // Exact match
        if ($name1 === $name2) {
            return 100;
        }
        
        // Case-insensitive match
        if (strtolower($name1) === strtolower($name2)) {
            return 95;
        }
        
        // Calculate Levenshtein distance
        $maxLength = max(strlen($name1), strlen($name2));
        $distance = levenshtein(strtolower($name1), strtolower($name2));
        
        // Convert distance to similarity percentage
        $similarity = (1 - ($distance / $maxLength)) * 100;
        
        // Bonus points for common abbreviations
        if ($this->hasCommonAbbreviations($name1, $name2)) {
            $similarity = min(100, $similarity + 10);
        }
        
        return (int) round($similarity);
    }

    /**
     * Create a manual mapping between integration customer and customer.
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @param Customer $customer
     * @param int $userId
     * @param string|null $notes
     * @return CustomerMapping
     */
    public function createManualMapping(
        IntegrationCustomer $integrationCustomer,
        Customer $customer,
        int $userId,
        ?string $notes = null
    ): CustomerMapping {
        $confidenceScore = $this->calculateSimilarity(
            $integrationCustomer->customer_name,
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

    /**
     * Get suggested matches for an unmapped integration customer.
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @param int $limit
     * @return array
     */
    public function getSuggestedMatches(IntegrationCustomer $integrationCustomer, int $limit = 5): array
    {
        $company = $integrationCustomer->integration->company;
        
        $existingCustomers = Customer::where('company_id', $company->company_id)
            ->where('status', 'active')
            ->get();
        
        $suggestions = [];
        
        foreach ($existingCustomers as $customer) {
            $score = $this->calculateSimilarity(
                $integrationCustomer->customer_name,
                $customer->canonical_name
            );
            
            if ($score > 50) { // Only suggest if > 50% similar
                $suggestions[] = [
                    'customer' => $customer,
                    'confidence_score' => $score,
                ];
            }
        }
        
        // Sort by confidence score descending
        usort($suggestions, fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);
        
        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Merge two customers into one.
     * 
     * @param Customer $primary
     * @param Customer $duplicate
     * @param int $userId
     * @return Customer
     */
    public function mergeCustomers(Customer $primary, Customer $duplicate, int $userId): Customer
    {
        // Move all integration customer mappings to primary
        CustomerMapping::where('customer_id', $duplicate->customer_id)
            ->update(['customer_id' => $primary->customer_id]);
        
        // Move all report schedules to primary
        $duplicate->reportSchedules()->update(['customer_id' => $primary->customer_id]);
        
        // Move all generated reports to primary
        $duplicate->generatedReports()->update(['customer_id' => $primary->customer_id]);
        
        // Soft delete duplicate
        $duplicate->status = 'inactive';
        $duplicate->save();
        
        return $primary;
    }

    /**
     * Normalize customer name for comparison.
     * 
     * @param string $name
     * @return string
     */
    private function normalizeName(string $name): string
    {
        // Remove common suffixes
        $name = preg_replace('/\s+(Pty Ltd|Pty\. Ltd\.|Ltd|Limited|Inc|Corp|Corporation)$/i', '', $name);
        
        // Remove extra whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Trim
        return trim($name);
    }

    /**
     * Check if names contain common abbreviations of each other.
     * 
     * @param string $name1
     * @param string $name2
     * @return bool
     */
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
