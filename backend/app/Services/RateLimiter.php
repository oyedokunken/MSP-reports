<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    private bool $useRedis;

    public function __construct()
    {
        // Fallback to cache if Redis is not available
        $this->useRedis = config('cache.default') === 'redis';
    }

    public function canMakeRequest(Integration $integration): bool
    {
        try {
            $limits = $this->getRateLimits($integration);
            $minuteKey = $this->getKey($integration, 'minute');
            $minuteCount = $this->get($minuteKey) ?? 0;

            if ($minuteCount >= $limits['requests_per_minute']) {
                return false;
            }

            $hourKey = $this->getKey($integration, 'hour');
            $hourCount = $this->get($hourKey) ?? 0;

            if ($hourCount >= $limits['requests_per_hour']) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Rate limiter check failed, allowing request', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);
            return true;  // Fail open to not block legitimate requests
        }
    }

    public function recordRequest(Integration $integration): void
    {
        try {
            $minuteKey = $this->getKey($integration, 'minute');
            $hourKey = $this->getKey($integration, 'hour');

            $this->increment($minuteKey, 60);
            $this->increment($hourKey, 3600);
        } catch (\Exception $e) {
            Log::error('Failed to record rate limit', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param Integration $integration
     * @return array{per_minute: array{limit: int, used: int, remaining: int, resets_in_seconds: int}, per_hour: array{limit: int, used: int, remaining: int, resets_in_seconds: int}}
     */
    public function getStatus(Integration $integration): array
    {
        try {
            $limits = $this->getRateLimits($integration);

            $minuteKey = $this->getKey($integration, 'minute');
            $hourKey = $this->getKey($integration, 'hour');

            $minuteCount = (int) ($this->get($minuteKey) ?? 0);
            $hourCount = (int) ($this->get($hourKey) ?? 0);

            $minuteTtlResult = $this->useRedis ? Redis::ttl($minuteKey) : 60;
            $hourTtlResult = $this->useRedis ? Redis::ttl($hourKey) : 3600;

            // Ensure we have integers for TTL
            $minuteTtl = (int) $minuteTtlResult;
            $hourTtl = (int) $hourTtlResult;

            return [
                'per_minute' => [
                    'limit' => $limits['requests_per_minute'],
                    'used' => (int) $minuteCount,
                    'remaining' => max(0, $limits['requests_per_minute'] - $minuteCount),
                    'resets_in_seconds' => $minuteTtl > 0 ? $minuteTtl : 0,
                ],
                'per_hour' => [
                    'limit' => $limits['requests_per_hour'],
                    'used' => (int) $hourCount,
                    'remaining' => max(0, $limits['requests_per_hour'] - $hourCount),
                    'resets_in_seconds' => $hourTtl > 0 ? $hourTtl : 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get rate limit status', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);

            return [
                'per_minute' => ['limit' => 60, 'used' => 0, 'remaining' => 60, 'resets_in_seconds' => 0],
                'per_hour' => ['limit' => 1000, 'used' => 0, 'remaining' => 1000, 'resets_in_seconds' => 0],
            ];
        }
    }

    public function waitForSlot(Integration $integration, int $maxWaitSeconds = 60): bool
    {
        $waited = 0;

        while (!$this->canMakeRequest($integration)) {
            if ($waited >= $maxWaitSeconds) {
                return false;
            }

            sleep(1);
            $waited++;
        }

        return true;
    }

    /**
     * @param Integration $integration
     * @return array{requests_per_minute: int, requests_per_hour: int}
     */
    private function getRateLimits(Integration $integration): array
    {
        $config = $integration->configuration ?? [];

        return [
            'requests_per_minute' => $config['rate_limits']['requests_per_minute'] ?? 60,
            'requests_per_hour' => $config['rate_limits']['requests_per_hour'] ?? 1000,
        ];
    }

    private function getKey(Integration $integration, string $period): string
    {
        return "rate_limit:integration:{$integration->integration_id}:{$period}";
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function get(string $key)
    {
        if ($this->useRedis) {
            return Redis::get($key);
        }
        return Cache::get($key);
    }

    private function increment(string $key, int $ttl): void
    {
        if ($this->useRedis) {
            Redis::incr($key);
            if (Redis::ttl($key) === -1) {
                Redis::expire($key, $ttl);
            }
        } else {
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, $ttl);
        }
    }
}
