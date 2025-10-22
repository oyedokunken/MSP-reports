<?php

namespace App\Integrations\Contracts;

use Carbon\Carbon;

interface IntegrationInterface
{
    public function authenticate(): bool;

    public function sync(): array;

    public function getCustomers(): array;

    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array;

    public function getAvailableHistoryRange(): ?Carbon;

    public function getMetadata(): array;

    public function disconnect(): void;

    public function healthCheck(): bool;

    public function getRateLimits(): array;
}
