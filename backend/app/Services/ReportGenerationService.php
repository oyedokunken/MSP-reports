<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportGenerationService
{

    public function generateReport(ReportSchedule $schedule): GeneratedReport
    {
        
        $generatedReport = GeneratedReport::create([
            'company_id' => $schedule->company_id,
            'customer_id' => $schedule->customer_id,
            'report_schedule_id' => $schedule->report_schedule_id,
            'generated_at' => now(),
            'report_period' => $this->calculateReportPeriod($schedule),
            'status' => 'generating',
        ]);

        try {
            
            $reportClass = $this->loadReportClass($schedule->report_type);

            if (!$reportClass) {
                throw new \Exception("Report type {$schedule->report_type} not found.");
            }

            
            if (!$reportClass->validate($schedule->customer, $this->calculateReportPeriod($schedule))) {
                throw new \Exception("Insufficient data to generate report.");
            }

            
            $reportData = $reportClass->generate($schedule->customer, $this->calculateReportPeriod($schedule));

            
            $reportData = $this->applyBranding($reportData, $schedule->company, $schedule->customer);

            
            $pdfPath = $this->generatePDF($reportData, $schedule);

            
            $generatedReport->update([
                'status' => 'completed',
                'file_path' => $pdfPath,
                'file_size' => Storage::disk('reports')->size($pdfPath),
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
                
                return [
                    'start' => $now->copy()->subDays(30),
                    'end' => $now,
                ];
        }
    }

    private function loadReportClass(string $reportType)
    {
        
        $reportClasses = [
            'uptime_report' => \App\Reports\UptimeReport\UptimeReport::class,
            'uptime_monthly' => \App\Reports\UptimeReport\UptimeReport::class,
            'security_summary' => \App\Reports\SecuritySummary\SecuritySummary::class,
        ];

        $className = $reportClasses[$reportType] ?? null;

        if ($className && class_exists($className)) {
            return new $className();
        }

        return null;
    }

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

    private function generatePDF(array $reportData, ReportSchedule $schedule): string
    {
        $filename = sprintf(
            '%s/%s_%s.pdf',
            $schedule->company_id,
            $schedule->customer_id,
            now()->format('Y-m-d_His')
        );

        // Generate HTML from template
        $html = $this->generateHTML($reportData, $schedule);

        // Generate PDF from HTML using DomPDF
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true); // Allow external images/CSS

        // Save PDF to storage
        Storage::disk('reports')->put($filename, $pdf->output());

        return $filename;
    }


    private function generateHTML(array $reportData, ReportSchedule $schedule): string
    {
        // Load the report class to get the correct template
        $reportClass = $this->loadReportClass($schedule->report_type);
        
        if ($reportClass && method_exists($reportClass, 'getTemplate')) {
            $templatePath = $reportClass->getTemplate();
            
            // Check if the template file exists
            if (file_exists($templatePath)) {
                // Render the specific report template
                return view()->file($templatePath, [
                    'reportData' => $reportData,
                    'schedule' => $schedule,
                    'customer' => $schedule->customer,
                    'branding' => $reportData['branding'] ?? [],
                    'metrics' => $reportData['metrics'] ?? [],
                    'period' => $reportData['period'] ?? [],
                    'data' => $reportData['data'] ?? [],
                    'alerts' => $reportData['alerts'] ?? [],
                ])->render();
            }
        }
        
        // Fallback to generic template if specific template not found
        return view('reports.template', [
            'reportData' => $reportData,
            'schedule' => $schedule,
            'branding' => $reportData['branding'] ?? [],
            'metrics' => $reportData['metrics'] ?? [],
            'period' => $reportData['period'] ?? [],
        ])->render();
    }

    private function calculateRetentionDate($company): \Carbon\Carbon
    {
        $retentionDays = $company->settings['data_retention']['generated_reports_months'] ?? 36;
        return now()->addMonths($retentionDays);
    }
}
