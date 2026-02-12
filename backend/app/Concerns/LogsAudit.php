<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\AuditLog;
use App\Services\AIWatcherService;
use Illuminate\Database\Eloquent\Model;

trait LogsAudit
{
    /**
     * Create an audit log entry.
     *
     * @param int $companyId
     * @param string $eventType
     * @param Model|null $target
     * @param array<string, mixed> $eventData
     * @param string $actorType
     * @param int|null $actorId
     */
    protected function audit(
        int $companyId,
        string $eventType,
        ?Model $target = null,
        array $eventData = [],
        string $actorType = 'System',
        ?int $actorId = null,
    ): void {
        try {
            $user = auth()->user();
            if ($user && $actorType === 'System') {
                $actorType = 'User';
                $actorId = $user->user_id;
            }

            AuditLog::create([
                'company_id' => $companyId,
                'event_type' => $eventType,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'target_type' => $target ? get_class($target) : null,
                'target_id' => $target?->getKey(),
                'event_data' => $eventData,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            // Trigger AI Watcher message for the actor
            try {
                app(AIWatcherService::class)->processAuditEvent(
                    $companyId,
                    $eventType,
                    $eventData,
                    $actorId,
                );
            } catch (\Throwable $e) {
                // AI Watcher is non-critical — never block audit logging
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to write audit log', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
