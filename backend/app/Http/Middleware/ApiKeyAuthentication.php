<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

class ApiKeyAuthentication
{
    /**
     * Handle an incoming request.
     * 
     * Authenticates requests using API keys instead of user sessions.
     * API keys have the same permission structure as users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for API key in Authorization header
        $apiKey = $request->bearerToken();

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'API key required.',
            ], 401);
        }

        // Hash the provided key to compare with stored hash
        $keyHash = hash('sha256', $apiKey);

        // Find the API key
        $apiKeyModel = ApiKey::where('key_hash', $keyHash)
            ->where('revoked', false)
            ->with('company')
            ->first();

        if (!$apiKeyModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or revoked API key.',
            ], 401);
        }

        // Check if company is active
        if (in_array($apiKeyModel->company->status, ['suspended'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company account is suspended.',
            ], 403);
        }

        // Check IP whitelist if configured
        if (!empty($apiKeyModel->ip_whitelist)) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $apiKeyModel->ip_whitelist)) {
                // Log unauthorized access attempt
                AuditLog::create([
                    'company_id' => $apiKeyModel->company_id,
                    'event_type' => 'api_key_unauthorized_ip',
                    'actor_type' => 'ApiKey',
                    'actor_id' => $apiKeyModel->api_key_id,
                    'event_data' => json_encode([
                        'ip_address' => $clientIp,
                        'allowed_ips' => $apiKeyModel->ip_whitelist,
                    ]),
                    'ip_address' => $clientIp,
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'IP address not whitelisted for this API key.',
                ], 403);
            }
        }

        // Check endpoint restrictions if configured
        if (!empty($apiKeyModel->allowed_endpoints)) {
            $currentPath = $request->path();
            $allowed = false;

            foreach ($apiKeyModel->allowed_endpoints as $pattern) {
                if (fnmatch($pattern, $currentPath)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This API key is not authorized for this endpoint.',
                ], 403);
            }
        }

        // Update last used timestamp
        $apiKeyModel->last_used_at = now();
        $apiKeyModel->save();

        // Store API key context in request
        $request->merge([
            '_api_key' => $apiKeyModel,
            '_company_id' => $apiKeyModel->company_id,
            '_permissions' => $apiKeyModel->permissions,
        ]);

        // Log API request
        AuditLog::create([
            'company_id' => $apiKeyModel->company_id,
            'event_type' => 'api_request',
            'actor_type' => 'ApiKey',
            'actor_id' => $apiKeyModel->api_key_id,
            'event_data' => json_encode([
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $next($request);
    }
}
