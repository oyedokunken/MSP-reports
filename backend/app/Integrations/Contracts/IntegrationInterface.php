<?php

namespace App\Integrations\Contracts;

use Carbon\Carbon;

interface IntegrationInterface
{
    /**
     * Authenticate and validate credentials.
     * 
     * @return bool
     * @throws \Exception if authentication fails
     */
    public function authenticate(): bool;

    /**
     * Pull latest current-state data from the integration.
     * 
     * @return array ['customers' => array, 'errors' => array]
     */
    public function sync(): array;

    /**
     * Get customer list in standardized format.
     * 
     * @return array
     */
    public function getCustomers(): array;

    /**
     * Pull historical data for a specific date range.
     * 
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array ['data' => array, 'errors' => array]
     */
    public function getHistoricalData(Carbon $startDate, Carbon $endDate): array;

    /**
     * Query how far back historical data is available.
     * 
     * @return Carbon|null Earliest available date, or null if not supported
     */
    public function getAvailableHistoryRange(): ?Carbon;

    /**
     * Get metadata about what data fields this integration provides.
     * 
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Clean up connection and revoke tokens if applicable.
     * 
     * @return void
     */
    public function disconnect(): void;

    /**
     * Test if connection is still valid.
     * 
     * @return bool
     */
    public function healthCheck(): bool;

    /**
     * Declare API rate limits for this integration.
     * 
     * @return array ['requests_per_minute' => int, 'requests_per_hour' => int]
     */
    public function getRateLimits(): array;
}
