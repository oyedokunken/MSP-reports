<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

use Carbon\Carbon;

interface IntegrationInterface extends HeartbeatInterface
{
    public function authenticate(): bool;

    /**
     * @return array<string, mixed>
     */
    public function sync(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCustomers(): array;

    /**
     * @return array<string, mixed>
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array;

    public function getAvailableHistoryRange(): ?Carbon;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    public function disconnect(): void;

    public function healthCheck(): bool;

    /**
     * @return array<string, int>
     */
    public function getRateLimits(): array;

    /**
     * Get the display template for rendering integration data in the frontend
     * Returns a structured template describing sections, components, and formatting
     * 
     * @return array<string, mixed>
     */
    public function getDisplayTemplate(): array;

    /**
     * Get preview data for this integration
     * 
     * @return array<string, mixed>
     */
    public function getPreviewData(?int $integrationCustomerId = null): array;

    /**
     * Get credential fields required for this integration
     * Returns array of field definitions for dynamic form generation
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getCredentialFields(): array;
}
