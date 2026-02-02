<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'canonical_name' => $this->canonical_name,
            'status' => $this->status,
            'is_static' => $this->is_static,
            'report_configuration' => $this->report_configuration,
            'report_destination' => $this->report_destination,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'mappings_count' => $this->whenCounted('customerMappings'),
        ];
    }
}
