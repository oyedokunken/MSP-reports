<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'audit_log_id' => $this->audit_log_id,
            'event_type' => $this->event_type,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'event_data' => $this->event_data,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
