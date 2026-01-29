<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EmbeddingService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.embedding.url', 'http://127.0.0.1:5050');
        $this->timeout = config('services.embedding.timeout', 30);
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful() && ($response->json('status') === 'healthy');
        } catch (\Exception $e) {
            Log::debug('Embedding service not available', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param array<int, array{id: int|string, name: string}> $unmapped
     * @param array<int, array{id: int|string, name: string}> $existing
     * @param float $threshold
     * @param int $topN
     * @return array<int, array{unmapped_id: int|string, unmapped_name: string, suggestions: array<int, array{id: int|string, name: string, score: float}>}>
     */
    public function findMatches(array $unmapped, array $existing, float $threshold = 0.6, int $topN = 3): array
    {
        if (empty($unmapped) || empty($existing)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/find-matches", [
                    'unmapped' => $unmapped,
                    'existing' => $existing,
                    'threshold' => $threshold,
                    'top_n' => $topN,
                ]);

            if ($response->successful()) {
                return $response->json('matches', []);
            }

            Log::warning('Embedding service find-matches failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Embedding service error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * @param array<int, string> $names
     * @return bool
     */
    public function warmupCache(array $names): bool
    {
        if (empty($names)) {
            return true;
        }

        try {
            $response = Http::timeout($this->timeout * 2)
                ->post("{$this->baseUrl}/warmup", [
                    'names' => $names,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Embedding warmup failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param array<int, string> $queries
     * @param array<int, string> $candidates
     * @return array<string, array<string, float>>
     */
    public function getSimilarity(array $queries, array $candidates): array
    {
        if (empty($queries) || empty($candidates)) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/similarity", [
                    'queries' => $queries,
                    'candidates' => $candidates,
                ]);

            if ($response->successful()) {
                return $response->json('similarities', []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Embedding similarity error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function isAgentAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/agent-status");
            return $response->successful() && ($response->json('available') === true);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param array<int, array{id: int|string, name: string}> $unmapped
     * @param array<int, array{id: int|string, name: string}> $existing
     * @return array<int, array{unmapped_id: int|string, unmapped_name: string, suggestions: array<int, array{id: int|string, name: string, score: float, reasoning?: string}>}>
     */
    public function findMatchesWithAgent(array $unmapped, array $existing): array
    {
        if (empty($unmapped) || empty($existing)) {
            return [];
        }

        try {
            // Agent matching is slower, use longer timeout
            $response = Http::timeout($this->timeout * 3)
                ->post("{$this->baseUrl}/agent-match", [
                    'unmapped' => $unmapped,
                    'existing' => $existing,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!($data['agent_available'] ?? false)) {
                    Log::debug('Agent not available', ['error' => $data['error'] ?? 'unknown']);
                    return [];
                }
                return $data['matches'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Agent match error', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
