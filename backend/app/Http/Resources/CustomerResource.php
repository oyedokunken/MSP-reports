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
        /** @var \App\Models\Customer $customer */
        $customer = $this->resource;
        
        return [
            'customer_id' => $customer->customer_id,
            'canonical_name' => $customer->canonical_name,
            'status' => $customer->status,
            'is_static' => $customer->is_static,
            'report_configuration' => $customer->report_configuration,
            'report_destination' => $customer->report_destination,
            'metadata' => $customer->metadata,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
            'mappings_count' => $this->whenCounted('customerMappings'),
        ];
    }
}
