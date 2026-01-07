<?php

namespace App\Services\Delivery\Contracts;

use App\Models\GeneratedReport;
use App\Models\Customer;
use App\Models\Company;

interface DeliveryDriverInterface
{
    /**
     * Get the unique identifier for this delivery method
     */
    public function getIdentifier(): string;

    /**
     * Get the display name for this delivery method
     */
    public function getDisplayName(): string;

    /**
     * Deliver the report using this method
     *
     * @param GeneratedReport $report The report to deliver
     * @param array $config The delivery configuration for this method
     * @param Company $company The company context
     * @param Customer $customer The customer receiving the report
     * @return array Result with 'success' boolean and optional 'message'
     * @throws \Exception on delivery failure
     */
    public function deliver(GeneratedReport $report, array $config, Company $company, Customer $customer): array;

    /**
     * Validate the configuration for this delivery method
     *
     * @param array $config The configuration to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateConfig(array $config): array;

    /**
     * Get the configuration schema for this delivery method
     * Used by frontend to render configuration forms
     *
     * @return array Configuration schema
     */
    public function getConfigSchema(): array;

    /**
     * Test the connection/configuration without actually delivering
     *
     * @param array $config The configuration to test
     * @param Company $company The company context
     * @return array Test result with 'success' boolean and 'message'
     */
    public function testConnection(array $config, Company $company): array;
}
