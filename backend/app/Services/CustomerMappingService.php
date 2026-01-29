<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CustomerMappingService
{
    protected ?EmbeddingService $embeddingService = null;

    public function __construct(?EmbeddingService $embeddingService = null)
    {
        $this->embeddingService = $embeddingService;
    }

    protected function getEmbeddingService(): ?EmbeddingService
    {
        if ($this->embeddingService === null && config('services.embedding.enabled', true)) {
            $this->embeddingService = app(EmbeddingService::class);
        }
        return $this->embeddingService;
    }

    /**
     * Auto-match with embedding fallback.
     * 
     * Strategy:
     * 1. Try fast Levenshtein-based matching first
     * 2. If no match found and embedding service is available, try semantic matching
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @param bool $useEmbeddings Whether to use embedding fallback (default: true)
     * @return array{customer: Customer, confidence_score: int, mapping_type: string}|null Match result with 'customer', 'confidence_score', 'mapping_type'
     */
    public function autoMatchWithEmbeddings(IntegrationCustomer $integrationCustomer, bool $useEmbeddings = true): ?array
    {
        // Step 1: Try fast Levenshtein matching first
        $levenshteinMatch = $this->autoMatch($integrationCustomer);
        
        if ($levenshteinMatch !== null) {
            Log::debug('Customer matched via Levenshtein', [
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'customer_name' => $integrationCustomer->name,
                'matched_to' => $levenshteinMatch['customer']->canonical_name,
                'score' => $levenshteinMatch['confidence_score'],
            ]);
            return $levenshteinMatch;
        }

        // Step 2: If no Levenshtein match and embeddings enabled, try semantic matching
        if (!$useEmbeddings || !config('services.embedding.enabled', true)) {
            return null;
        }

        $embeddingService = $this->getEmbeddingService();
        if ($embeddingService === null || !$embeddingService->isAvailable()) {
            Log::debug('Embedding service not available for fallback matching');
            return null;
        }

        $company = $integrationCustomer->integration->company;
        $embeddingThreshold = config('services.embedding.threshold', 0.6);

        // Get existing customers
        $existingCustomers = Cache::remember("customer_mapping_list_{$company->company_id}", 60, function () use ($company) {
            return Customer::where('company_id', $company->company_id)
                ->where('status', 'active')
                ->get();
        });

        if ($existingCustomers->isEmpty()) {
            return null;
        }

        // Prepare data for embedding service
        $unmapped = [
            ['id' => $integrationCustomer->integration_customer_id, 'name' => $integrationCustomer->name]
        ];
        $existing = $existingCustomers->map(fn($c) => [
            'id' => $c->customer_id,
            'name' => $c->canonical_name
        ])->toArray();

        // Call embedding service
        $matches = $embeddingService->findMatches($unmapped, $existing, $embeddingThreshold, 1);

        if (empty($matches) || empty($matches[0]['suggestions'])) {
            Log::debug('No embedding match found', [
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'customer_name' => $integrationCustomer->name,
            ]);
            return null;
        }

        $bestMatch = $matches[0]['suggestions'][0];
        $matchedCustomer = $existingCustomers->firstWhere('customer_id', $bestMatch['id']);

        if ($matchedCustomer === null) {
            return null;
        }

        Log::info('Customer matched via embeddings', [
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'customer_name' => $integrationCustomer->name,
            'matched_to' => $matchedCustomer->canonical_name,
            'score' => $bestMatch['score'],
        ]);

        return [
            'customer' => $matchedCustomer,
            'confidence_score' => (int) round($bestMatch['score']),
            'mapping_type' => 'auto_embedding',
        ];
    }

    /**
     * Auto-match using LLM agent with tools.
     * This is the most intelligent but slowest method - use as last resort.
     * 
     * The agent can reason about:
     * - Initials (TTS → Territory Technology Solutions)
     * - Abbreviations (Corp → Corporation)
     * - Typos and variations
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @return array{customer: Customer, confidence_score: int, mapping_type: string, reasoning: string}|null Match result
     */
    public function autoMatchWithAgent(IntegrationCustomer $integrationCustomer): ?array
    {
        $embeddingService = $this->getEmbeddingService();
        if ($embeddingService === null || !$embeddingService->isAgentAvailable()) {
            Log::debug('Agent not available for matching');
            return null;
        }

        $company = $integrationCustomer->integration->company;

        $existingCustomers = Cache::remember("customer_mapping_list_{$company->company_id}", 60, function () use ($company) {
            return Customer::where('company_id', $company->company_id)
                ->where('status', 'active')
                ->get();
        });

        if ($existingCustomers->isEmpty()) {
            return null;
        }

        $unmapped = [
            ['id' => $integrationCustomer->integration_customer_id, 'name' => $integrationCustomer->name]
        ];
        $existing = $existingCustomers->map(fn($c) => [
            'id' => $c->customer_id,
            'name' => $c->canonical_name
        ])->toArray();

        $matches = $embeddingService->findMatchesWithAgent($unmapped, $existing);

        if (empty($matches) || empty($matches[0]['suggestions'])) {
            Log::debug('No agent match found', [
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'customer_name' => $integrationCustomer->name,
            ]);
            return null;
        }

        $bestMatch = $matches[0]['suggestions'][0];
        $matchedCustomer = $existingCustomers->firstWhere('customer_id', $bestMatch['id']);

        if ($matchedCustomer === null) {
            return null;
        }

        Log::info('Customer matched via agent', [
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'customer_name' => $integrationCustomer->name,
            'matched_to' => $matchedCustomer->canonical_name,
            'score' => $bestMatch['score'],
            'reasoning' => $bestMatch['reasoning'] ?? '',
        ]);

        return [
            'customer' => $matchedCustomer,
            'confidence_score' => (int) round($bestMatch['score']),
            'mapping_type' => 'auto_agent',
            'reasoning' => $bestMatch['reasoning'] ?? '',
        ];
    }

    /**
     * Full auto-match pipeline: Levenshtein → Embeddings → Agent
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @param bool $useEmbeddings Whether to use embedding fallback
     * @param bool $useAgent Whether to use agent fallback (slowest)
     * @return array{customer: Customer, confidence_score: int, mapping_type: string, reasoning?: string}|null Match result
     */
    public function autoMatchFull(IntegrationCustomer $integrationCustomer, bool $useEmbeddings = true, bool $useAgent = false): ?array
    {
        // Tier 1: Fast Levenshtein matching
        $match = $this->autoMatch($integrationCustomer);
        if ($match !== null) {
            return $match;
        }

        // Tier 2: Embedding-based semantic matching
        if ($useEmbeddings) {
            $match = $this->autoMatchWithEmbeddings($integrationCustomer, false); // Don't recurse
            if ($match !== null) {
                return $match;
            }
        }

        // Tier 3: LLM Agent with tools (slowest, most intelligent)
        if ($useAgent) {
            $match = $this->autoMatchWithAgent($integrationCustomer);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Batch auto-match multiple integration customers using embeddings.
     * More efficient than calling autoMatchWithEmbeddings one by one.
     * 
     * @param array<int, IntegrationCustomer> $integrationCustomers Array of IntegrationCustomer models
     * @param int $companyId Company ID for fetching existing customers
     * @return array<int, array{customer: Customer, confidence_score: int, mapping_type: string}> Map of integration_customer_id => match result
     */
    public function batchAutoMatchWithEmbeddings(array $integrationCustomers, int $companyId): array
    {
        if (empty($integrationCustomers)) {
            return [];
        }

        $results = [];
        $needsEmbedding = [];

        // Step 1: Try Levenshtein first for all
        foreach ($integrationCustomers as $ic) {
            $match = $this->autoMatch($ic);
            if ($match !== null) {
                $results[$ic->integration_customer_id] = $match;
            } else {
                $needsEmbedding[] = $ic;
            }
        }

        // Step 2: Batch process remaining with embeddings
        if (empty($needsEmbedding) || !config('services.embedding.enabled', true)) {
            return $results;
        }

        $embeddingService = $this->getEmbeddingService();
        if ($embeddingService === null || !$embeddingService->isAvailable()) {
            return $results;
        }

        $existingCustomers = Cache::remember("customer_mapping_list_{$companyId}", 60, function () use ($companyId) {
            return Customer::where('company_id', $companyId)
                ->where('status', 'active')
                ->get();
        });

        if ($existingCustomers->isEmpty()) {
            return $results;
        }

        $embeddingThreshold = config('services.embedding.threshold', 0.6);

        // Prepare batch data
        $unmapped = array_map(fn($ic) => [
            'id' => $ic->integration_customer_id,
            'name' => $ic->name
        ], $needsEmbedding);

        $existing = $existingCustomers->map(fn($c) => [
            'id' => $c->customer_id,
            'name' => $c->canonical_name
        ])->toArray();

        // Call embedding service for batch
        $matches = $embeddingService->findMatches($unmapped, $existing, $embeddingThreshold, 1);

        // Process results
        foreach ($matches as $match) {
            if (empty($match['suggestions'])) {
                continue;
            }

            $bestSuggestion = $match['suggestions'][0];
            $matchedCustomer = $existingCustomers->firstWhere('customer_id', $bestSuggestion['id']);

            if ($matchedCustomer !== null) {
                $results[(int) $match['unmapped_id']] = [
                    'customer' => $matchedCustomer,
                    'confidence_score' => (int) round($bestSuggestion['score']),
                    'mapping_type' => 'auto_embedding',
                ];

                Log::info('Customer batch-matched via embeddings', [
                    'integration_customer_id' => $match['unmapped_id'],
                    'customer_name' => $match['unmapped_name'],
                    'matched_to' => $matchedCustomer->canonical_name,
                    'score' => $bestSuggestion['score'],
                ]);
            }
        }

        return $results;
    }

    /**
     * @param IntegrationCustomer $integrationCustomer
     * @return array{customer: Customer, confidence_score: int, mapping_type: string}|null
     */
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

    /**
     * @param IntegrationCustomer $integrationCustomer
     * @param int $limit
     * @return array<int, array{customer: Customer, confidence_score: int}>
     */
    public function getSuggestedMatches(IntegrationCustomer $integrationCustomer, int $limit = 5): array
    {
        $company = $integrationCustomer->integration->company;

        $existingCustomers = Customer::with('integrationCustomers')
            ->where('company_id', $company->company_id)
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
     * 
     * @param IntegrationCustomer $integrationCustomer
     * @param string $integrationType
     * @return array{external_id: string, name: string, unified_customer_id: int, canonical_name: string}
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
