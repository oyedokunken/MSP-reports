<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class ReportNamingService
{
    /**
     * Default naming pattern if none configured
     */
    public const DEFAULT_PATTERN = '{customer_name}_{report_type}_{date}';

    /**
     * Available tokens for pattern replacement
     */
    public const AVAILABLE_TOKENS = [
        '{customer_name}' => 'Customer name (sanitized)',
        '{report_type}' => 'Report type slug',
        '{date}' => 'Date (YYYY-MM-DD)',
        '{month}' => 'Month name',
        '{year}' => '4-digit year',
        '{frequency}' => 'Report frequency',
        '{timestamp}' => 'Full timestamp (YYYY-MM-DD_HHmmss)',
    ];

    /**
     * Generate a filename for a report based on company naming pattern
     */
    public function generateFilename(ReportSchedule $schedule, Company $company, ?Customer $customer = null, ?Carbon $date = null): string
    {
        $filename = $this->getDownloadName($schedule, $company, $customer, $date);
        
        // Build full path: company_id/report_type/filename.pdf
        return sprintf(
            '%s/%s/%s.pdf',
            $schedule->company_id,
            $schedule->report_type,
            $filename
        );
    }

    /**
     * Get just the sanitized filename (without path) for a report
     */
    public function getDownloadName(ReportSchedule $schedule, Company $company, ?Customer $customer = null, ?Carbon $date = null): string
    {
        $pattern = $this->getPattern($company);
        $tokens = $this->buildTokens($schedule, $customer, $date);
        
        $filename = $this->parsePattern($pattern, $tokens);
        return $this->sanitizeForFilename($filename);
    }

    /**
     * Get naming pattern from company settings or use default
     */
    public function getPattern(Company $company): string
    {
        $settings = $company->settings ?? [];
        $pattern = $settings['report_settings']['naming_pattern'] ?? null;
        
        return $pattern ?: self::DEFAULT_PATTERN;
    }

    /**
     * Build token replacement array from schedule data
     * 
     * @return array<string, string>
     */
    protected function buildTokens(ReportSchedule $schedule, ?Customer $customer = null, ?Carbon $date = null): array
    {
        $now = $date ?: Carbon::now();
        $customer = $customer ?: $schedule->customer;
        
        return [
            '{customer_name}' => $customer->canonical_name ?? 'Unknown',
            '{report_type}' => $schedule->report_type,
            '{date}' => $now->format('Y-m-d'),
            '{month}' => $now->format('F'),
            '{year}' => $now->format('Y'),
            '{frequency}' => $schedule->frequency->value,
            '{timestamp}' => $now->format('Y-m-d_His'),
        ];
    }

    /**
     * Replace tokens in pattern with actual values
     * 
     * @param string $pattern
     * @param array<string, string> $tokens
     * @return string
     */
    public function parsePattern(string $pattern, array $tokens): string
    {
        return str_replace(
            array_keys($tokens),
            array_values($tokens),
            $pattern
        );
    }

    /**
     * Sanitize string for use as filename
     * Removes/replaces characters that are invalid in filenames
     */
    public function sanitizeForFilename(string $value): string
    {
        // Replace spaces with underscores
        $value = str_replace(' ', '_', $value);
        
        // Remove or replace invalid filename characters
        $value = preg_replace('/[<>:"\/\\|?*]/', '', $value);
        
        // Replace multiple underscores with single
        $value = preg_replace('/_+/', '_', $value);
        
        // Trim underscores from ends
        $value = trim($value, '_');
        
        // Limit length (keep reasonable filename length)
        if (strlen($value) > 200) {
            $value = substr($value, 0, 200);
        }
        
        return $value ?: 'report';
    }

    /**
     * Get available tokens for UI display
     * 
     * @return array<string, string>
     */
    public function getAvailableTokens(): array
    {
        return self::AVAILABLE_TOKENS;
    }

    /**
     * Validate a naming pattern
     * Returns true if pattern is valid, false otherwise
     */
    public function validatePattern(string $pattern): bool
    {
        // Pattern must not be empty
        if (empty(trim($pattern))) {
            return false;
        }

        // Pattern should contain at least one token
        $hasToken = false;
        foreach (array_keys(self::AVAILABLE_TOKENS) as $token) {
            if (str_contains($pattern, $token)) {
                $hasToken = true;
                break;
            }
        }

        return $hasToken;
    }

    /**
     * Generate a preview filename for UI display
     */
    public function generatePreview(string $pattern, ?string $customerName = null): string
    {
        $sampleTokens = [
            '{customer_name}' => $customerName ?? 'Acme_Corp',
            '{report_type}' => 'monthly-summary',
            '{date}' => Carbon::now()->format('Y-m-d'),
            '{month}' => Carbon::now()->format('F'),
            '{year}' => Carbon::now()->format('Y'),
            '{frequency}' => 'monthly',
            '{timestamp}' => Carbon::now()->format('Y-m-d_His'),
        ];

        $filename = $this->parsePattern($pattern, $sampleTokens);
        return $this->sanitizeForFilename($filename) . '.pdf';
    }
}
