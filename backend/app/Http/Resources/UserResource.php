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
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        
        return [
            'user_id' => $user->user_id,
            'name' => $user->name,
            'email' => $user->email,
            'company_id' => $user->company_id,
            'role_id' => $user->role_id,
            'role_name' => $this->whenLoaded('role', fn() => $user->role->name),
            'role' => $this->whenLoaded('role', fn() => [
                'name' => $user->role->name,
                'role_id' => $user->role->role_id ?? $user->role_id,
            ]),
            'auth_method' => $user->auth_method,
            'status' => $user->status,
            'permissions' => $user->permissions,
            'profile_picture_url' => $user->profile_picture ? '/storage/' . $user->profile_picture : null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
