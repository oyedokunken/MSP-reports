<?php

declare(strict_types=1);

namespace App\Reports\Contracts;

use App\Models\Customer;
use Carbon\Carbon;

interface ReportInterface
{
    /**
     * Get required data types for this report
     * 
     * @return array<string> e.g., ['uptime_metric', 'backup_status']
     */
    public function getRequiredDataTypes(): array;

    /**
     * Get minimum days of historical data required
     * 
     * @return int Days of history needed
     */
    public function getRequiredHistoricalRange(): int;

    /**
     * Validate that sufficient data exists to generate report
     * 
     * @param Customer $customer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return bool
     */
    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool;

    /**
     * Generate the report data
     * 
     * @param Customer $customer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array<string, mixed> $options Additional configuration options
     * @return array<string, mixed> Report data structure
     */
    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array;

    /**
     * Get the Blade template path for this report
     * 
     * @return string Template path
     */
    public function getTemplate(): string;

    /**
     * Get report metadata (name, description, etc.)
     * 
     * @return array{
     *   id: string,
     *   name: string,
     *   description: string,
     *   category: string,
     *   data_types: array<string>,
     *   frequencies: array<string>,
     *   formats: array<string>
     * }
     */
    public function getMetadata(): array;
}
