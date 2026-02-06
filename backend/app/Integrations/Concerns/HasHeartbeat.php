<?php

declare(strict_types=1);

namespace App\Integrations\Concerns;

trait HasHeartbeat
{
    /** @var callable|null */
    protected $heartbeatCallback;
    protected ?int $currentSyncLogId = null;

    public function setHeartbeatCallback(callable $callback): void
    {
        $this->heartbeatCallback = $callback;
    }

    public function setSyncLogId(int $id): void
    {
        $this->currentSyncLogId = $id;
    }

    public function sendHeartbeat(int $count = 0): void
    {
        if ($this->heartbeatCallback) {
            call_user_func($this->heartbeatCallback, $count);
        }
    }
}
