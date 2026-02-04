<?php

declare(strict_types=1);

namespace App\Actions\AuditLog;

use App\Models\AuditLog;
use Carbon\Carbon;

final class GetAuditLogStatsAction
{
    /**
     * @return array{
     *     total_logs: int,
     *     logs_today: int,
     *     unique_actors: int,
     *     critical_events: int
     * }
     */
    public function execute(int $companyId): array
    {
        $today = Carbon::today();

        $totalLogs = AuditLog::where('company_id', $companyId)->count();
        $logsToday = AuditLog::where('company_id', $companyId)
            ->where('created_at', '>=', $today)
            ->count();
        
        $uniqueActors = AuditLog::where('company_id', $companyId)
            ->distinct('actor_id')
            ->count('actor_id');

        // Critical events - example heuristic
        $criticalEvents = AuditLog::where('company_id', $companyId)
            ->whereIn('event_type', [
                'user_deleted', 
                'api_key_created', 
                'failed_login_attempt',
                'security_settings_updated'
            ])
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'total_logs' => $totalLogs,
            'logs_today' => $logsToday,
            'unique_actors' => $uniqueActors,
            'critical_events' => $criticalEvents,
        ];
    }
}
