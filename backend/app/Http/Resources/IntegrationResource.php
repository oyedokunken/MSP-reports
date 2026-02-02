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
        /** @var \App\Models\Integration $integration */
        $integration = $this->resource;
        
        $latestSync = $integration->syncLogs->first();
        
        // Calculate next sync time
        $integrationService = app(\App\Services\IntegrationService::class);
        $nextSyncAt = $integrationService->calculateNextSyncAt($integration);
        
        return [
            'integration_id' => $integration->integration_id,
            'integration_type' => $integration->integration_type,
            'display_name' => $integration->display_name,
            'status' => $integration->status,
            'is_source_of_truth' => $integration->is_source_of_truth,
            'source_label' => $integration->is_source_of_truth ? '✅ Source of Truth' : null,
            'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
            'last_sync_status' => $integration->last_sync_status,
            'next_sync_at' => $nextSyncAt,
            'error_message' => $integration->error_message,
            'configuration' => $integration->configuration,
            'historical_import_status' => $integration->historical_import_status,
            'historical_import_progress' => $integration->historical_import_progress,
            'last_historical_sync_at' => $integration->last_historical_sync_at?->toIso8601String(),
            'sync_schedule' => $integration->sync_schedule,
            'created_at' => $integration->created_at?->toIso8601String(),
            'updated_at' => $integration->updated_at?->toIso8601String(),
            'unmapped_customers_count' => $integration->unmapped_customers_count ?? 0,
            'total_customers_count' => $integration->total_customers_count ?? 0,
            'records_synced' => $latestSync ? $latestSync->records_synced : 0,
            'last_error' => $latestSync && $latestSync->status === 'failed' ? ($latestSync->error_messages[0] ?? null) : null,
        ];
    }
}
