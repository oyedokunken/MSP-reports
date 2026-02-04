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
use App\Http\Resources\ApiKeyResource;
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

        $apiKeys = $query->get();

        return $this->success(ApiKeyResource::collection($apiKeys));
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
        
        // Save a preview (first 8 and last 4 characters)
        $keyPreview = substr($plainKey, 0, 8) . '...' . substr($plainKey, -4);

        $apiKey = ApiKey::create([
            'company_id' => $user->company_id,
            'customer_id' => $validated['customer_id'] ?? null,
            'created_by_user_id' => $user->user_id,
            'display_name' => $validated['display_name'],
            'key_preview' => $keyPreview,
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

        return $this->success(
            new ApiKeyResource($apiKey),
            'API key created successfully',
            201,
            ['api_key' => $plainKey]
        );
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

        return $this->success(null, 'API key revoked successfully.');
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

        return $this->success(null, 'API key deleted successfully.');
    }
}
