<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\GotenbergService;
use App\Services\ReportRegistry;
use Carbon\Carbon;

class ReportGenerationService
{
    protected GotenbergService $gotenberg;
    protected ReportRegistry $registry;

    public function __construct(GotenbergService $gotenberg, ReportRegistry $registry)
    {
        $this->gotenberg = $gotenberg;
        $this->registry = $registry;
    }

    public function generateReport(ReportSchedule $schedule, ?Carbon $customStartDate = null, ?Carbon $customEndDate = null): GeneratedReport
    {
        // Use custom dates if provided, otherwise calculate from schedule
        if ($customStartDate && $customEndDate) {
            $period = [
                'start' => $customStartDate,
                'end' => $customEndDate,
            ];
        } else {
            $period = $this->calculateReportPeriod($schedule);
        }
        
        $generatedReport = GeneratedReport::create([
            'company_id' => $schedule->company_id,
            'customer_id' => $schedule->customer_id,
            'report_schedule_id' => $schedule->report_schedule_id,
            'generated_at' => now(),
            'report_period' => $period,
            'status' => 'generating',
        ]);

        try {
            // Load report class with company_id
            $reportClass = $this->loadReportClass($schedule->report_type, $schedule->company_id);

            if (!$reportClass) {
                throw new \Exception("Report type {$schedule->report_type} not found.");
            }
            
            // Get report period
            $startDate = $period['start'];
            $endDate = $period['end'];
            
            // Validate data availability
            if (!$reportClass->validate($schedule->customer, $startDate, $endDate)) {
                throw new \Exception("Insufficient data to generate report.");
            }
            
            // Prepare options from schedule_config
            $options = [];
            if ($schedule->schedule_config) {
                $options = is_array($schedule->schedule_config) ? $schedule->schedule_config : json_decode($schedule->schedule_config, true);
            }
            
            // Generate report with Carbon dates and options
            $reportData = $reportClass->generate($schedule->customer, $startDate, $endDate, $options);
            
            // Apply branding (if not already in report data)
            if (!isset($reportData['branding'])) {
                $reportData = $this->applyBranding($reportData, $schedule->company, $schedule->customer);
            }
            
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

    private function loadReportClass(string $reportType, int $companyId)
    {
        // Use registry to get report instance
        $report = $this->registry->get($reportType, $companyId);

        if (!$report) {
            Log::warning("Report type not found in registry", [
                'report_type' => $reportType,
                'company_id' => $companyId,
                'available_reports' => array_keys($this->registry->all()),
            ]);
        }

        return $report;
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
        $fullDirPath = Storage::disk('reports')->path($directory);
        
        if (!file_exists($fullDirPath)) {
            // Use native PHP mkdir for better control and error handling
            if (!mkdir($fullDirPath, 0775, true)) {
                Log::error('Failed to create report directory', [
                    'directory' => $directory,
                    'full_path' => $fullDirPath,
                    'parent_exists' => file_exists(dirname($fullDirPath)),
                    'parent_writable' => is_writable(dirname($fullDirPath)),
                ]);
                throw new \Exception("Failed to create directory: {$fullDirPath}");
            }
            
            // Ensure proper ownership (www user in container)
            @chown($fullDirPath, 'www');
            @chgrp($fullDirPath, 'www');
            
            Log::info('Created report directory', [
                'directory' => $directory,
                'full_path' => $fullDirPath,
                'permissions' => decoct(fileperms($fullDirPath) & 0777),
            ]);
        }

        $html = $this->generateHTML($reportData, $schedule);

        // Use Gotenberg to generate PDF
        $pdfContent = $this->gotenberg->htmlToPdf($html, [
            'paper_width' => '8.27',  // A4 width in inches
            'paper_height' => '11.69', // A4 height in inches
            'margin_top' => '0',
            'margin_bottom' => '0',
            'margin_left' => '0',
            'margin_right' => '0',
            'print_background' => true,
            'prefer_css_page_size' => false,
        ]);

        // Log PDF content size for debugging
        Log::info('PDF generation completed', [
            'filename' => $filename,
            'content_size' => strlen($pdfContent),
        ]);
        
        // Write the PDF file using native PHP for better error handling
        $fullPath = Storage::disk('reports')->path($filename);
        
        Log::info('Attempting to write PDF file', [
            'filename' => $filename,
            'full_path' => $fullPath,
            'directory_exists' => file_exists(dirname($fullPath)),
            'directory_writable' => is_writable(dirname($fullPath)),
            'content_size' => strlen($pdfContent),
        ]);
        
        $bytesWritten = file_put_contents($fullPath, $pdfContent);
        
        if ($bytesWritten === false) {
            Log::error('Failed to write PDF file', [
                'filename' => $filename,
                'full_path' => $fullPath,
                'directory' => dirname($fullPath),
                'directory_exists' => file_exists(dirname($fullPath)),
                'directory_writable' => is_writable(dirname($fullPath)),
                'disk_free_space' => disk_free_space(dirname($fullPath)),
            ]);
            throw new \Exception("Failed to write PDF file: {$filename}");
        }
        
        Log::info('PDF file written successfully', [
            'filename' => $filename,
            'full_path' => $fullPath,
            'bytes_written' => $bytesWritten,
            'file_exists' => file_exists($fullPath),
        ]);
        
        // Set proper permissions and ownership
        chmod($fullPath, 0644);
        @chown($fullPath, 'www');
        @chgrp($fullPath, 'www');

        return $filename;
    }


    private function generateHTML(array $reportData, ReportSchedule $schedule): string
    {
        $reportClass = $this->loadReportClass($schedule->report_type, $schedule->company_id);
        
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
                    'generated_at' => $reportData['generated_at'] ?? now()->toDateTimeString(),
                    'included_reports' => $reportData['included_reports'] ?? [],
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
