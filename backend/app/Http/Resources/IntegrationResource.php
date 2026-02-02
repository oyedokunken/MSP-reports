<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'integration_id' => $this->integration_id,
            'integration_type' => $this->integration_type,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'last_sync_status' => $this->last_sync_status,
            'error_message' => $this->error_message,
            'configuration' => $this->configuration,
            'is_source_of_truth' => $this->is_source_of_truth,
            'historical_import_status' => $this->historical_import_status,
            'historical_import_progress' => $this->historical_import_progress,
            'last_historical_sync_at' => $this->last_historical_sync_at?->toIso8601String(),
            'sync_schedule' => $this->sync_schedule,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customers_count' => $this->whenCounted('integrationCustomers'),
        ];
    }
}
