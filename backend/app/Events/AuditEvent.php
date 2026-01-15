<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditEvent
{
    use Dispatchable, SerializesModels;

    public int $companyId;
    public string $eventType;
    public ?string $actorType;
    public ?int $actorId;
    public ?string $targetType;
    public $targetId;
    public array $eventData;
    public ?string $ipAddress;
    public ?string $userAgent;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $companyId,
        string $eventType,
        array $eventData = [],
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        $targetId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ) {
        $this->companyId = $companyId;
        $this->eventType = $eventType;
        $this->eventData = $eventData;
        $this->actorType = $actorType;
        $this->actorId = $actorId;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->ipAddress = $ipAddress ?: request()->ip();
        $this->userAgent = $userAgent ?: request()->userAgent();
    }
}
