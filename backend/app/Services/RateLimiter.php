<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use App\Models\Integration;

class RateLimiter
{
    public function canMakeRequest(Integration $integration): bool
    {
        $limits = $this->getRateLimits($integration);
        $minuteKey = $this->getRedisKey($integration, 'minute');
        $minuteCount = Redis::get($minuteKey) ?? 0;
        
        if ($minuteCount >= $limits['requests_per_minute']) {
            return false;
        }
        
        $hourKey = $this->getRedisKey($integration, 'hour');
        $hourCount = Redis::get($hourKey) ?? 0;
        
        if ($hourCount >= $limits['requests_per_hour']) {
            return false;
        }
        
        return true;
    }

    public function recordRequest(Integration $integration): void
    {
        $minuteKey = $this->getRedisKey($integration, 'minute');
        $hourKey = $this->getRedisKey($integration, 'hour');
        
        Redis::incr($minuteKey);
        Redis::incr($hourKey);
        
        if (Redis::ttl($minuteKey) === -1) {
            Redis::expire($minuteKey, 60);
        }
        
        if (Redis::ttl($hourKey) === -1) {
            Redis::expire($hourKey, 3600);
        }
    }

    public function getStatus(Integration $integration): array
    {
        $limits = $this->getRateLimits($integration);
   
        $minuteKey = $this->getRedisKey($integration, 'minute');
        $hourKey = $this->getRedisKey($integration, 'hour');
        
        $minuteCount = Redis::get($minuteKey) ?? 0;
        $hourCount = Redis::get($hourKey) ?? 0;
        
        $minuteTtl = Redis::ttl($minuteKey);
        $hourTtl = Redis::ttl($hourKey);
        
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

    private function getRateLimits(Integration $integration): array
    {
        $config = $integration->configuration ?? [];
        
        return [
            'requests_per_minute' => $config['rate_limits']['requests_per_minute'] ?? 60,
            'requests_per_hour' => $config['rate_limits']['requests_per_hour'] ?? 1000,
        ];
    }

    private function getRedisKey(Integration $integration, string $period): string
    {
        return "rate_limit:integration:{$integration->integration_id}:{$period}";
    }
}
