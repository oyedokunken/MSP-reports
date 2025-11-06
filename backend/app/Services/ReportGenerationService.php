<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\GotenbergService;

class ReportGenerationService
{
    protected GotenbergService $gotenberg;

    public function __construct(GotenbergService $gotenberg)
    {
        $this->gotenberg = $gotenberg;
    }

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
            
            // Verify file exists and get size
            if (!Storage::disk('reports')->exists($pdfPath)) {
                Log::error('PDF file not found after generation', [
                    'pdf_path' => $pdfPath,
                    'full_path' => Storage::disk('reports')->path($pdfPath),
                    'disk_root' => Storage::disk('reports')->path(''),
                ]);
                throw new \Exception("Generated PDF file not found: {$pdfPath}");
            }
            
            // Try to get file size, with fallback to direct file access
            $fileSize = Storage::disk('reports')->size($pdfPath);
            
            if ($fileSize === false || $fileSize === null || $fileSize === 0) {
                // Fallback: try getting size directly from filesystem
                $fullPath = Storage::disk('reports')->path($pdfPath);
                if (file_exists($fullPath)) {
                    $fileSize = filesize($fullPath);
                    Log::warning('Used fallback method to get file size', [
                        'pdf_path' => $pdfPath,
                        'file_size' => $fileSize,
                    ]);
                } else {
                    Log::error('File size check failed', [
                        'pdf_path' => $pdfPath,
                        'full_path' => $fullPath,
                        'exists' => file_exists($fullPath),
                    ]);
                    throw new \Exception("Unable to determine file size for: {$pdfPath}");
                }
            }
            
            $generatedReport->update([
                'status' => 'completed',
                'file_path' => $pdfPath,
                'file_size' => $fileSize,
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
            'backup_status' => \App\Reports\BackupStatus\BackupStatus::class,
            'ticket_summary' => \App\Reports\TicketSummary\TicketSummary::class,
            'monthly_comprehensive' => \App\Reports\MonthlyComprehensive\MonthlyComprehensive::class,
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
        // Organize by: company_id/report_type/customer_id_timestamp.pdf
        $filename = sprintf(
            '%s/%s/%s_%s.pdf',
            $schedule->company_id,
            $schedule->report_type,
            $schedule->customer_id,
            now()->format('Y-m-d_His')
        );

        // Ensure directory exists with proper permissions
        $directory = sprintf('%s/%s', $schedule->company_id, $schedule->report_type);
        if (!Storage::disk('reports')->exists($directory)) {
            Storage::disk('reports')->makeDirectory($directory, 0755, true);
        }

        $html = $this->generateHTML($reportData, $schedule);

        // Use Gotenberg to generate PDF
        $pdfContent = $this->gotenberg->htmlToPdf($html, [
            'paper_width' => '8.27',  // A4 width in inches
            'paper_height' => '11.69', // A4 height in inches
            'margin_top' => '0.39',
            'margin_bottom' => '0.39',
            'margin_left' => '0.39',
            'margin_right' => '0.39',
            'print_background' => true,
            'prefer_css_page_size' => false,
        ]);

        // Log PDF content size for debugging
        Log::info('PDF generation completed', [
            'filename' => $filename,
            'content_size' => strlen($pdfContent),
        ]);
        
        // Write the PDF file
        $written = Storage::disk('reports')->put($filename, $pdfContent);
        
        if (!$written) {
            Log::error('Failed to write PDF to storage', [
                'filename' => $filename,
                'disk' => 'reports',
                'root' => Storage::disk('reports')->path(''),
            ]);
            throw new \Exception("Failed to write PDF file to storage: {$filename}");
        }
        
        // Ensure file exists before setting permissions
        $fullPath = Storage::disk('reports')->path($filename);
        Log::info('Checking PDF file existence', [
            'filename' => $filename,
            'full_path' => $fullPath,
            'exists' => file_exists($fullPath),
        ]);
        
        if (file_exists($fullPath)) {
            chmod($fullPath, 0644);
        } else {
            throw new \Exception("PDF file was written but not found at: {$fullPath}");
        }

        return $filename;
    }


    private function generateHTML(array $reportData, ReportSchedule $schedule): string
    {
        $reportClass = $this->loadReportClass($schedule->report_type);
        
        if ($reportClass && method_exists($reportClass, 'getTemplate')) {
            $templatePath = $reportClass->getTemplate();
            
            Log::info('Generating report HTML', [
                'report_type' => $schedule->report_type,
                'report_class' => get_class($reportClass),
                'template_path' => $templatePath,
                'file_exists' => file_exists($templatePath),
            ]);
            
            if (file_exists($templatePath)) {
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
