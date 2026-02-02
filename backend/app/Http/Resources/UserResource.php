<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'company_id' => $this->company_id,
            'role_id' => $this->role_id,
            'role_name' => $this->whenLoaded('role', fn() => $this->role->name),
            'auth_method' => $this->auth_method,
            'status' => $this->status,
            'permissions' => $this->permissions,
            'profile_picture_url' => $this->profile_picture ? asset('storage/' . $this->profile_picture) : null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
