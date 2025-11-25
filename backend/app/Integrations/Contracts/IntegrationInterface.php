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

    /**
     * Get the display template for rendering integration data in the frontend
     * Returns a structured template describing sections, components, and formatting
     */
    public function getDisplayTemplate(): array;

    /**
     * Get preview data for a specific customer or integration instance
     * Returns both the data and any customer-specific context
     */
    public function getPreviewData(?int $integrationCustomerId = null): array;
}
