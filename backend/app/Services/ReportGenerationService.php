<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportGenerationService
{
    /**
     * Generate a report for a schedule.
     * 
     * @param ReportSchedule $schedule
     * @return GeneratedReport
     */
    public function generateReport(ReportSchedule $schedule): GeneratedReport
    {
        // Create generated report record
        $generatedReport = GeneratedReport::create([
            'company_id' => $schedule->company_id,
            'customer_id' => $schedule->customer_id,
            'report_schedule_id' => $schedule->report_schedule_id,
            'generated_at' => now(),
            'report_period' => $this->calculateReportPeriod($schedule),
            'status' => 'generating',
        ]);

        try {
            // Load report class
            $reportClass = $this->loadReportClass($schedule->report_type);

            if (!$reportClass) {
                throw new \Exception("Report type {$schedule->report_type} not found.");
            }

            // Validate data availability
            if (!$reportClass->validate($schedule->customer, $this->calculateReportPeriod($schedule))) {
                throw new \Exception("Insufficient data to generate report.");
            }

            // Generate report
            $reportData = $reportClass->generate($schedule->customer, $this->calculateReportPeriod($schedule));

            // Apply white-label branding
            $reportData = $this->applyBranding($reportData, $schedule->company, $schedule->customer);

            // Generate PDF
            $pdfPath = $this->generatePDF($reportData, $schedule);

            // Update generated report
            $generatedReport->update([
                'status' => 'completed',
                'file_path' => $pdfPath,
                'file_size' => Storage::size($pdfPath),
                'retention_until' => $this->calculateRetentionDate($schedule->company),
            ]);

            return $generatedReport;

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'schedule_id' => $schedule->report_schedule_id,
                'error' => $e->getMessage(),
            ]);

            $generatedReport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the report period based on schedule frequency.
     * 
     * @param ReportSchedule $schedule
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    private function calculateReportPeriod(ReportSchedule $schedule): array
    {
        $now = now();

        switch ($schedule->frequency) {
            case 'daily':
                return [
                    'start' => $now->copy()->subDay()->startOfDay(),
                    'end' => $now->copy()->subDay()->endOfDay(),
                ];

            case 'weekly':
                return [
                    'start' => $now->copy()->subWeek()->startOfWeek(),
                    'end' => $now->copy()->subWeek()->endOfWeek(),
                ];

            case 'monthly':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth(),
                ];

            case 'yearly':
                return [
                    'start' => $now->copy()->subYear()->startOfYear(),
                    'end' => $now->copy()->subYear()->endOfYear(),
                ];

            default:
                // On-demand: last 30 days
                return [
                    'start' => $now->copy()->subDays(30),
                    'end' => $now,
                ];
        }
    }

    /**
     * Load report class by type.
     * 
     * @param string $reportType
     * @return mixed|null
     */
    private function loadReportClass(string $reportType)
    {
        // Map report types to classes
        $reportClasses = [
            'uptime_monthly' => \App\Reports\UptimeReport\UptimeReport::class,
            'security_summary' => \App\Reports\SecuritySummary\SecuritySummary::class,
        ];

        $className = $reportClasses[$reportType] ?? null;

        if ($className && class_exists($className)) {
            return new $className();
        }

        return null;
    }

    /**
     * Apply white-label branding to report data.
     * 
     * @param array $reportData
     * @param \App\Models\Company $company
     * @param Customer $customer
     * @return array
     */
    private function applyBranding(array $reportData, $company, Customer $customer): array
    {
        $whiteLabel = $company->settings['white_label'] ?? [];
        $customerBranding = $customer->report_configuration['report_branding'] ?? [];

        $reportData['branding'] = [
            'company_name' => $whiteLabel['company_name'] ?? $company->name,
            'company_logo' => $whiteLabel['logo_url'] ?? null,
            'customer_logo' => $customerBranding['customer_logo_url'] ?? null,
            'primary_color' => $whiteLabel['primary_color'] ?? '#1a56db',
            'secondary_color' => $whiteLabel['secondary_color'] ?? '#7c3aed',
            'report_title' => $customerBranding['report_title'] ?? 'Monthly Service Report',
            'footer_text' => $whiteLabel['footer_text'] ?? '',
            'footer_notes' => $customerBranding['footer_notes'] ?? '',
        ];

        return $reportData;
    }

    /**
     * Generate PDF from report data.
     * 
     * @param array $reportData
     * @param ReportSchedule $schedule
     * @return string File path
     */
    private function generatePDF(array $reportData, ReportSchedule $schedule): string
    {
        // TODO: Implement actual PDF generation using DomPDF or similar
        // For now, create a placeholder

        $filename = sprintf(
            'reports/%s/%s_%s.pdf',
            $schedule->company_id,
            $schedule->customer_id,
            now()->format('Y-m-d_His')
        );

        // Placeholder: Store JSON data as file
        Storage::put($filename, json_encode($reportData));

        return $filename;
    }

    /**
     * Calculate retention date for report.
     * 
     * @param \App\Models\Company $company
     * @return \Carbon\Carbon
     */
    private function calculateRetentionDate($company): \Carbon\Carbon
    {
        $retentionDays = $company->settings['data_retention']['generated_reports_months'] ?? 36;
        return now()->addMonths($retentionDays);
    }
}
