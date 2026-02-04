<?php

declare(strict_types=1);

namespace App\Actions\AuditLog;

use Illuminate\Database\Eloquent\Collection;
use App\Models\AuditLog;

final class ExportAuditLogsAction
{
    /**
     * @param Collection<int, AuditLog> $logs
     * @return string
     */
    public function execute(Collection $logs): string
    {
        $handle = fopen('php://temp', 'w+');
        
        if ($handle === false) {
            throw new \RuntimeException('Failed to open temporary file for CSV generation');
        }
        
        // CSV Header
        fputcsv($handle, ['ID', 'Event Type', 'Actor Type', 'Actor ID', 'Target Type', 'Target ID', 'Data', 'IP Address', 'Created At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->audit_log_id,
                $log->event_type,
                $log->actor_type,
                $log->actor_id,
                $log->target_type,
                $log->target_id,
                is_array($log->event_data) ? json_encode($log->event_data) : $log->event_data,
                $log->ip_address,
                $log->created_at->toDateTimeString(),
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content ?: '';
    }
}
