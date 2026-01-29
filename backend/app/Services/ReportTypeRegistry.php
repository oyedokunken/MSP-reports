<?php

namespace App\Services;

use App\Models\Customer;

class ReportTypeRegistry
{
    /**
     * Get all defined report types and their requirements
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getReportTypes(): array
    {
        return [
            [
                'type' => 'autotask_summary',
                'name' => 'Autotask Monthly Summary',
                'description' => 'Comprehensive summary of tickets, time entries, and billing',
                'required_integrations' => ['autotask'],
                'available_sections' => [
                    ['id' => 'tickets', 'label' => 'Ticket Summary', 'default' => true],
                    ['id' => 'time_entries', 'label' => 'Time Entries', 'default' => true],
                    ['id' => 'billing', 'label' => 'Billing Summary', 'default' => false],
                    ['id' => 'contracts', 'label' => 'Contract Status', 'default' => true],
                ],
            ],
            [
                'type' => 'itglue_report',
                'name' => 'IT Glue Documentation Report',
                'description' => 'Documentation status and updates',
                'required_integrations' => ['itglue'],
                'available_sections' => [
                    ['id' => 'configurations', 'label' => 'Configuration Items', 'default' => true],
                    ['id' => 'passwords', 'label' => 'Password Updates', 'default' => false],
                    ['id' => 'documents', 'label' => 'Document Changes', 'default' => true],
                ],
            ],
            [
                'type' => 'datto_backup',
                'name' => 'Datto Backup Report',
                'description' => 'Backup status and success rates',
                'required_integrations' => ['datto'],
                'available_sections' => [
                    ['id' => 'backup_status', 'label' => 'Backup Status', 'default' => true],
                    ['id' => 'success_rates', 'label' => 'Success Rates', 'default' => true],
                    ['id' => 'storage_usage', 'label' => 'Storage Usage', 'default' => true],
                    ['id' => 'failed_backups', 'label' => 'Failed Backups', 'default' => true],
                ],
            ],
            [
                'type' => 'combined_monthly',
                'name' => 'Combined Monthly Report',
                'description' => 'All-in-one report from multiple integrations',
                'required_integrations' => ['autotask'],  // At least Autotask required
                'optional_integrations' => ['datto', 'itglue'],
                'available_sections' => [
                    ['id' => 'executive_summary', 'label' => 'Executive Summary', 'default' => true],
                    ['id' => 'tickets', 'label' => 'Support Tickets', 'default' => true],
                    ['id' => 'backups', 'label' => 'Backup Status', 'default' => true, 'requires' => 'datto'],
                    ['id' => 'documentation', 'label' => 'Documentation', 'default' => false, 'requires' => 'itglue'],
                ],
            ],
        ];
    }

    /**
     * Get report types available for a specific customer based on their active integrations
     * 
     * @param int $customerId
     * @return array<int, array<string, mixed>>
     */
    public static function getAvailableForCustomer(int $customerId): array
    {
        $customer = Customer::with('integrationCustomers.integration')->find($customerId);

        if (!$customer) {
            return [];
        }

        // Get customer's active integration types (e.g., ['autotask', 'datto'])
        $customerIntegrations = $customer
            ->integrationCustomers
            ->map(function ($ic) {
                /** @var \App\Models\IntegrationCustomer $ic */
                return strtolower($ic->integration->integration_type);
            })
            ->unique()
            ->values()
            ->toArray();

        // Filter report types
        $allReportTypes = self::getReportTypes();
        $availableReportTypes = [];

        foreach ($allReportTypes as $reportType) {
            $requiredIntegrations = $reportType['required_integrations'];

            // Check if customer has all required integrations
            // Using array_diff to see if any required integrations are missing from customer's list
            $missingRequired = array_diff($requiredIntegrations, $customerIntegrations);

            if (empty($missingRequired)) {
                // Filter sections based on available integrations
                $reportType['available_sections'] = array_filter(
                    $reportType['available_sections'],
                    function ($section) use ($customerIntegrations) {
                        if (!isset($section['requires'])) {
                            return true;
                        }
                        return in_array($section['requires'], $customerIntegrations);
                    }
                );

                // Re-index sections array
                $reportType['available_sections'] = array_values($reportType['available_sections']);

                $availableReportTypes[] = $reportType;
            }
        }

        return $availableReportTypes;
    }
}
