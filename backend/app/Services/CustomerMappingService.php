<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\Company;

class CustomerMappingService
{
    public function autoMatch(IntegrationCustomer $integrationCustomer): ?array
    {
        $company = $integrationCustomer->integration->company;
        $threshold = $company->settings['customer_mapping']['auto_match_threshold'] ?? 85;
        
        $existingCustomers = Customer::where('company_id', $company->company_id)
            ->where('status', 'active')
            ->get();
        
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
        
        // Exact match
        if ($name1 === $name2) {
            return 100;
        }
        
        // Case-insensitive exact match
        if (strtolower($name1) === strtolower($name2)) {
            return 95;
        }
        
        // Extract core company name (before common suffixes)
        $core1 = $this->extractCoreName($original1);
        $core2 = $this->extractCoreName($original2);
        
        // If core names match exactly, high confidence
        if (strtolower($core1) === strtolower($core2)) {
            return 90;
        }
        
        // Calculate Levenshtein distance on full names
        $maxLength = max(strlen($name1), strlen($name2));
        $distance = levenshtein(strtolower($name1), strtolower($name2));
        $similarity = (1 - ($distance / $maxLength)) * 100;
        
        // Also calculate similarity on core names
        $coreMaxLength = max(strlen($core1), strlen($core2));
        $coreDistance = levenshtein(strtolower($core1), strtolower($core2));
        $coreSimilarity = (1 - ($coreDistance / $coreMaxLength)) * 100;
        
        // Use the better of the two scores
        $similarity = max($similarity, $coreSimilarity);
        
        // Check for common abbreviations and give bonus
        if ($this->hasCommonAbbreviations($original1, $original2)) {
            $similarity = min(100, $similarity + 20);
        }
        
        // Bonus if core names are very similar (handles "NewCorp Tech" vs "NewCorp Technologies")
        if ($coreSimilarity >= 70) {
            $similarity = min(100, $similarity + 15);
        }
        
        return (int) round($similarity);
    }
    
    private function extractCoreName(string $name): string
    {
        // Remove common company suffixes to get core name
        $suffixes = [
            'Pty Ltd', 'Pty. Ltd.', 'Pty Ltd.', 
            'Limited', 'Ltd', 'Ltd.',
            'Incorporated', 'Inc', 'Inc.',
            'Corporation', 'Corp', 'Corp.',
            'Company', 'Co', 'Co.',
            'Proprietary',
            'Technologies', 'Technology', 'Tech',
            'Solutions', 'Services',
            'Group', 'International',
            'Australia', 'Australian',
        ];
        
        $core = $name;
        foreach ($suffixes as $suffix) {
            // Remove suffix if it appears at the end (case-insensitive)
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
            
            // Show suggestions even for low scores (helps user see all options)
            // Note: Auto-matching still requires ≥85% threshold
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
        // DON'T remove suffixes - they're part of the company name!
        // Just normalize whitespace and case for comparison
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
