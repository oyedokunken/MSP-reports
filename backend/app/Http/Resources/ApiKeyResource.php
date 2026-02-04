<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\ApiKey $apiKey */
        $apiKey = $this->resource;
        
        return [
            'api_key_id' => $apiKey->api_key_id,
            'display_name' => $apiKey->display_name,
            'key_preview' => $apiKey->key_preview,
            'customer_id' => $apiKey->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn() => $apiKey->customer->canonical_name),
            'created_by' => $this->whenLoaded('createdBy', fn() => $apiKey->createdBy->name),
            'created_at' => $apiKey->created_at->toIso8601String(),
            'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
            'permissions' => $apiKey->permissions,
            'ip_whitelist' => $apiKey->ip_whitelist,
            'allowed_endpoints' => $apiKey->allowed_endpoints,
            'revoked' => $apiKey->revoked,
            'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
        ];
    }
}
