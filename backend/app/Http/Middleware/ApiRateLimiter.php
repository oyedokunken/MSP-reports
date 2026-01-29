<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    /**
     * Rate limits per endpoint type (requests per minute)
     * @var array<string, int>
     */
    protected array $limits = [
        'default' => 60,
        'auth' => 10,
        'sync' => 30,
        'report' => 20,
        'heavy' => 10,
    ];

    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveKey($request);
        $limit = $this->limits[$type] ?? $this->limits['default'];
        
        $current = (int) Cache::get($key, 0);
        
        if ($current >= $limit) {
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'limit' => $limit,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ], 429, [
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => Cache::get($key . ':reset', time() + 60),
                'Retry-After' => 60,
            ]);
        }

        // Increment counter
        if ($current === 0) {
            Cache::put($key, 1, now()->addMinute());
            Cache::put($key . ':reset', time() + 60, now()->addMinute());
        } else {
            Cache::increment($key);
        }

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $limit - $current - 1));
        $response->headers->set('X-RateLimit-Reset', Cache::get($key . ':reset', time() + 60));

        return $response;
    }

    protected function resolveKey(Request $request): string
    {
        // Use API key if present, otherwise IP
        $identifier = $request->bearerToken() 
            ? 'apikey:' . substr(hash('sha256', $request->bearerToken()), 0, 16)
            : 'ip:' . $request->ip();

        return 'ratelimit:' . $identifier;
    }
}
