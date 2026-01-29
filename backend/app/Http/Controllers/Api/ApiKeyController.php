<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiKeyController extends Controller
{
    /**
     * List all API keys for the authenticated user's company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $apiKeys = ApiKey::where('company_id', $user->company_id)
            ->with(['customer:customer_id,canonical_name', 'createdBy:user_id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'api_key_id' => $key->api_key_id,
                    'display_name' => $key->display_name,
                    'customer_id' => $key->customer_id,
                    'customer_name' => $key->customer?->canonical_name,
                    'created_by' => $key->createdBy->name,
                    'created_at' => $key->created_at->toIso8601String(),
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'permissions' => $key->permissions,
                    'ip_whitelist' => $key->ip_whitelist,
                    'allowed_endpoints' => $key->allowed_endpoints,
                    'revoked' => $key->revoked,
                    'revoked_at' => $key->revoked_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $apiKeys,
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,customer_id',
            'permissions' => 'nullable|array',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'allowed_endpoints' => 'nullable|array',
            'allowed_endpoints.*' => 'string',
        ]);

        // Generate a secure random API key
        $plainKey = 'msp_' . Str::random(48);
        $keyHash = hash('sha256', $plainKey);

        $apiKey = ApiKey::create([
            'company_id' => $user->company_id,
            'customer_id' => $validated['customer_id'] ?? null,
            'created_by_user_id' => $user->user_id,
            'display_name' => $validated['display_name'],
            'key_hash' => $keyHash,
            'permissions' => $validated['permissions'] ?? null,
            'ip_whitelist' => $validated['ip_whitelist'] ?? null,
            'allowed_endpoints' => $validated['allowed_endpoints'] ?? null,
            'revoked' => false,
        ]);

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_created',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $apiKey->api_key_id,
            'event_data' => [
                'display_name' => $apiKey->display_name,
                'customer_id' => $apiKey->customer_id,
                'has_ip_whitelist' => !empty($apiKey->ip_whitelist),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'API key created successfully',
            'api_key' => $plainKey,
            'data' => [
                'api_key_id' => $apiKey->api_key_id,
                'display_name' => $apiKey->display_name,
                'customer_id' => $apiKey->customer_id,
            ],
        ], 201);
    }

    /**
     * Revoke an API key
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $id)
            ->first();

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'API key not found',
            ], 404);
        }

        if ($apiKey->revoked) {
            return response()->json([
                'status' => 'error',
                'message' => 'API key is already revoked',
            ], 400);
        }

        $apiKey->update([
            'revoked' => true,
            'revoked_at' => now(),
            'revoked_by_user_id' => $user->user_id,
        ]);

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_revoked',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $apiKey->api_key_id,
            'event_data' => [
                'display_name' => $apiKey->display_name,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Delete an API key permanently
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $id)
            ->first();

        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'API key not found',
            ], 404);
        }

        $displayName = $apiKey->display_name;
        $apiKey->delete();

        // Audit log
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'api_key_deleted',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'ApiKey',
            'target_id' => $id,
            'event_data' => [
                'display_name' => $displayName,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'API key deleted successfully',
        ]);
    }
}
