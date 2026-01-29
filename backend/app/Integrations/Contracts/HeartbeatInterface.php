<?php

namespace App\Integrations\Contracts;

interface HeartbeatInterface
{
    public function setHeartbeatCallback(callable $callback): void;
    public function setSyncLogId(int $id): void;
    public function sendHeartbeat(): void;
}
