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
        return [
            'api_key_id' => $this->api_key_id,
            'display_name' => $this->display_name,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn() => $this->customer->canonical_name),
            'created_by' => $this->whenLoaded('createdBy', fn() => $this->createdBy->name),
            'created_at' => $this->created_at->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'permissions' => $this->permissions,
            'ip_whitelist' => $this->ip_whitelist,
            'allowed_endpoints' => $this->allowed_endpoints,
            'revoked' => $this->revoked,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
