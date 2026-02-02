<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Http\Requests\ApiKey\StoreApiKeyRequest;
use App\Http\Requests\ApiKey\GetApiKeysRequest;
use App\Http\Requests\ApiKey\RevokeApiKeyRequest;
use App\Http\Requests\ApiKey\DeleteApiKeyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiKeyController extends Controller
{
    /**
     * List all API keys for the authenticated user's company
     */
    public function index(GetApiKeysRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $query = ApiKey::where('company_id', $user->company_id)
            ->with(['customer:customer_id,canonical_name', 'createdBy:user_id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($validated['search'])) {
            $query->where('display_name', 'like', '%' . $validated['search'] . '%');
        }

        if (!empty($validated['customer_id'])) {
            $query->where('customer_id', $validated['customer_id']);
        }

        $apiKeys = $query->get()
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
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

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
    public function revoke(RevokeApiKeyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $id)
            ->first();

        // No need for "if (!$apiKey)" because authorize() handles it

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
    public function destroy(DeleteApiKeyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $apiKey = ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $id)
            ->first();

        // No need for "if (!$apiKey)" because authorize() handles it

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
