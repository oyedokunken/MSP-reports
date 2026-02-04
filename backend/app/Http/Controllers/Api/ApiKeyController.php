<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Actions\ApiKey\CreateApiKeyAction;
use App\Actions\ApiKey\RevokeApiKeyAction;
use App\Actions\ApiKey\DeleteApiKeyAction;
use App\Http\Requests\ApiKey\StoreApiKeyRequest;
use App\Http\Requests\ApiKey\GetApiKeysRequest;
use App\Http\Requests\ApiKey\RevokeApiKeyRequest;
use App\Http\Requests\ApiKey\DeleteApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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
    public function store(StoreApiKeyRequest $request, CreateApiKeyAction $action): JsonResponse
    {
        /** @var array{display_name: string, customer_id?: int|null, permissions?: array<string, mixed>|null, ip_whitelist?: array<string>|null, allowed_endpoints?: array<string>|null} $validated */
        $validated = $request->validated();

        $result = $action->execute(
            $request->user(),
            $validated,
            $request->ip() ?? '',
            $request->userAgent() ?? ''
        );

        return $this->success(
            new ApiKeyResource($result['apiKey']),
            'API key created successfully',
            201,
            ['api_key' => $result['plainKey']]
        );
    }

    /**
     * Revoke an API key
     */
    public function revoke(RevokeApiKeyRequest $request, int $id, RevokeApiKeyAction $action): JsonResponse
    {
        try {
            $apiKey = $action->execute(
                $request->user(),
                $id,
                $request->ip() ?? '',
                $request->userAgent() ?? ''
            );

            return $this->success(new ApiKeyResource($apiKey), 'API key revoked successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Delete an API key permanently
     */
    public function destroy(DeleteApiKeyRequest $request, int $id, DeleteApiKeyAction $action): JsonResponse
    {
        try {
            $action->execute(
                $request->user(),
                $id,
                $request->ip() ?? '',
                $request->userAgent() ?? ''
            );

            return $this->success(null, 'API key deleted successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}
