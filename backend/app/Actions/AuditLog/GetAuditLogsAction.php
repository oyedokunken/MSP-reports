<?php

declare(strict_types=1);

namespace App\Actions\AuditLog;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

final class GetAuditLogsAction
{
    /**
     * @param array{
     *     search?: string,
     *     event_type?: string,
     *     actor_type?: string,
     *     target_type?: string,
     *     date_from?: string,
     *     date_to?: string
     * } $filters
     * @return Builder<AuditLog>
     */
    public function execute(int $companyId, array $filters): Builder
    {
        $query = AuditLog::where('company_id', $companyId)
            ->orderBy('created_at', 'desc');

        // Search Filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('actor_type', 'like', "%{$search}%")
                  ->orWhere('target_type', 'like', "%{$search}%")
                  ->orWhere('event_data', 'like', "%{$search}%");
            });
        }

        // Event Type Filter
        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        // Actor Type Filter
        if (!empty($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }

        // Target Type Filter
        if (!empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }

        // Date Range Filter
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }
}
