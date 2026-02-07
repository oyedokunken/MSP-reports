<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class RecordAuditLog
{
    /**
     * Handle the event synchronously to ensure audit logs are always written.
     */
    public function handle(AuditEvent $event): void
    {
        AuditLog::create([
            'company_id'  => $event->companyId,
            'event_type'  => $event->eventType,
            'actor_type'  => $event->actorType,
            'actor_id'    => $event->actorId,
            'target_type' => $event->targetType,
            'target_id'   => $event->targetId,
            'event_data'  => $event->eventData,
            'ip_address'  => $event->ipAddress,
            'user_agent'  => $event->userAgent,
        ]);
    }
}
