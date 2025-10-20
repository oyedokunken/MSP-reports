<?php

namespace App\Reports\Contracts;

use App\Models\Company;
use App\Models\Customer;

interface ReportInterface
{
    /**
     * Return the data sources (integration types or data types) this report needs.
     */
    public static function getRequiredDataSources(): array;

    /**
     * Return number of days of historical data required.
     */
    public static function getRequiredHistoricalRange(): int;

    /**
     * Validate if data is sufficient to generate the report.
     */
    public function validate(Customer $customer): bool;

    /**
     * Generate the report for a specific customer and time period.
     */
    public function generate(Customer $customer, string $period): string;

    /**
     * Return the Blade template to render for the report.
     */
    public function getTemplate(): string;

    /**
     * Deliver the generated report (email, webhook, etc.).
     */
    public function deliver(Company $company, Customer $customer, string $filePath): void;
}
