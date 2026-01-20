<?php

namespace App\Reports\Contracts;

use App\Models\Company;
use App\Models\Customer;
use Carbon\Carbon;

abstract class BaseReport implements ReportInterface
{
    protected int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Default validation - can be overridden by specific reports
     */
    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Default implementation - always valid for testing
        // In production, specific reports should override this to check for required data
        \Log::info("Validating report", [
            'report_class' => get_class($this),
            'customer_id' => $customer->customer_id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
        
        return true;
    }

    /**
     * Helper to get company instance
     */
    protected function getCompany(): Company
    {
        return Company::findOrFail($this->companyId);
    }

    /**
     * Helper to get company's white-label settings
     */
    protected function getBranding(): array
    {
        $company = $this->getCompany();
        return $company->settings['white_label'] ?? [];
    }

    /**
     * Helper to format dates consistently
     */
    protected function formatDate(Carbon $date): string
    {
        return $date->format('F j, Y');
    }

    /**
     * Helper to format date ranges
     */
    protected function formatDateRange(Carbon $start, Carbon $end): string
    {
        // If it's a single day (Daily)
        if ($start->toDateString() === $end->toDateString()) {
            return $start->format('F j, Y');
        }

        // If it's a full month (Monthly)
        if ($start->day === 1 && $end->isSameDay($start->copy()->endOfMonth())) {
            return $start->format('F Y');
        }

        // If it's a full year (Yearly)
        if ($start->day === 1 && $start->month === 1 && $end->isSameDay($start->copy()->endOfYear())) {
            return $start->format('Y');
        }

        return $this->formatDate($start) . ' - ' . $this->formatDate($end);
    }

    /**
     * Helper to format time consistently
     */
    protected function formatTime(Carbon $time): string
    {
        return $time->format('g:i A');
    }

    /**
     * Helper to format datetime
     */
    protected function formatDateTime(Carbon $dateTime): string
    {
        return $dateTime->format('F j, Y g:i A');
    }

    /**
     * Helper to calculate percentage
     */
    protected function calculatePercentage(float $value, float $total): float
    {
        if ($total == 0) {
            return 0;
        }
        return round(($value / $total) * 100, 2);
    }

    /**
     * Helper to format file size
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Helper to format duration in minutes to human readable
     */
    protected function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        $result = $hours . ' hour' . ($hours != 1 ? 's' : '');
        if ($mins > 0) {
            $result .= ' ' . $mins . ' minute' . ($mins != 1 ? 's' : '');
        }
        
        return $result;
    }

    /**
     * Check if a section should be included based on template configuration
     * 
     * @param string $sectionId The section ID to check
     * @param array $options The options array passed to generate()
     * @return bool True if section should be included
     */
    protected function shouldIncludeSection(string $sectionId, array $options): bool
    {
        // If no included_sections specified, include all sections
        if (!isset($options['included_sections']) || empty($options['included_sections'])) {
            return true;
        }

        // If 'all' is in included_sections, include everything
        if (in_array('all', $options['included_sections'])) {
            return true;
        }

        // Check if this specific section is in the included list
        return in_array($sectionId, $options['included_sections']);
    }

    /**
     * Filter report data to only include selected sections
     * 
     * @param array $reportData The full report data
     * @param array $options The options array with included_sections
     * @return array Filtered report data
     */
    protected function filterSections(array $reportData, array $options): array
    {
        // If no filtering needed, return as-is
        if (!isset($options['included_sections']) || empty($options['included_sections'])) {
            return $reportData;
        }

        // If 'all' is selected, return everything
        if (in_array('all', $options['included_sections'])) {
            return $reportData;
        }

        // Filter out sections not in included_sections
        $includedSections = $options['included_sections'];
        $filtered = $reportData;

        // Mark which sections to include in the template
        $filtered['sections_to_render'] = $includedSections;

        return $filtered;
    }

    /**
     * Get default metadata structure (can be overridden)
     */
    public function getMetadata(): array
    {
        return [
            'id' => 'unknown',
            'name' => 'Unknown Report',
            'description' => 'No description provided',
            'category' => 'general',
            'data_types' => $this->getRequiredDataTypes(),

            'formats' => ['pdf'],
        ];
    }

    /**
     * Check if integration has data for the given frequency
     * 
     * @param int $customerId Customer ID to check
     * @param string $frequency Frequency to check (monthly, weekly, daily, yearly, on-demand)
     * @return bool True if data exists for the frequency period
     */
    public static function hasDataForFrequency(int $customerId, string $frequency = 'monthly'): bool
    {
        if ($frequency === 'on-demand') {
            return true;
        }
        
        $dateRange = static::getDateRangeForFrequency($frequency);
        
        return static::hasDataInRange(
            $customerId, 
            $dateRange['start'], 
            $dateRange['end']
        );
    }

    /**
     * Check if integration has any data in the last 30 days
     * Used for UI availability indicators
     */
    public static function hasRecentData(int $customerId): bool
    {
        $now = Carbon::now();
        return static::hasDataInRange(
            $customerId,
            $now->copy()->subDays(30),
            $now
        );
    }

    /**
     * Get date range based on frequency
     * 
     * @param string $frequency Frequency type
     * @return array Array with 'start' and 'end' Carbon instances
     */
    protected static function getDateRangeForFrequency(string $frequency): array
    {
        $now = Carbon::now();
        
        return match($frequency) {
            'monthly' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ],
            'weekly' => [
                'start' => $now->copy()->subWeek()->startOfWeek(),
                'end' => $now->copy()->subWeek()->endOfWeek(),
            ],
            'daily' => [
                'start' => $now->copy()->subDay()->startOfDay(),
                'end' => $now->copy()->subDay()->endOfDay(),
            ],
            'yearly' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear(),
            ],
            'on-demand' => [
                'start' => $now->copy()->subDays(30),
                'end' => $now,
            ],
            default => [
                'start' => $now->copy()->subMonth(),
                'end' => $now,
            ],
        };
    }

    /**
     * Check if data exists in date range for this report type
     * 
     * @param int $customerId Customer ID
     * @param Carbon $startDate Start date
     * @param Carbon $endDate End date
     * @return bool True if data exists
     */
    protected static function hasDataInRange(int $customerId, Carbon $startDate, Carbon $endDate): bool
    {
        // Get report config from child class
        $configPath = dirname((new \ReflectionClass(static::class))->getFileName()) . '/config.php';
        
        if (!file_exists($configPath)) {
            return false;
        }
        
        $config = require $configPath;
        $dataTypes = $config['data_types'] ?? [];
        $requiredIntegrations = $config['required_integrations'] ?? [];
        
        if (empty($dataTypes) || empty($requiredIntegrations)) {
            return false;
        }
        
        // Get customer's integration customer IDs for required integrations
        $customer = \App\Models\Customer::find($customerId);
        if (!$customer) {
            return false;
        }
        
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function($q) use ($requiredIntegrations) {
                $q->whereIn('integration_type', $requiredIntegrations)
                  ->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();
        
        if (empty($integrationCustomerIds)) {
            return false;
        }
        
        // Check if data exists in the date range
        return \App\Models\IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $dataTypes)
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->whereBetween('data_timestamp', [$startDate, $endDate])
            ->exists();
    }
}
