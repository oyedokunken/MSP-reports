<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Services\GotenbergService;
use App\Services\ReportNamingService;
use App\Services\ReportRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReportGenerationService
{
    protected GotenbergService $gotenberg;
    protected ReportRegistry $registry;
    protected ReportNamingService $namingService;

    public function __construct(GotenbergService $gotenberg, ReportRegistry $registry, ReportNamingService $namingService)
    {
        $this->gotenberg = $gotenberg;
        $this->registry = $registry;
        $this->namingService = $namingService;
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
                throw new \Exception('Insufficient data to generate report.');
            }

            // Prepare options from schedule_config
            $options = [];
            if ($schedule->schedule_config) {
                $options = is_array($schedule->schedule_config) ? $schedule->schedule_config : json_decode($schedule->schedule_config, true);
            }

            // Add frequency to options so report can adjust title dynamically
            $options['frequency'] = $schedule->frequency ?? 'monthly';

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
            Log::warning('Report type not found in registry', [
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

        // Convert logo URLs to base64 for PDF embedding
        $companyLogo = null;
        if (isset($whiteLabel['logo_url']) && $whiteLabel['logo_url']) {
            $companyLogo = $this->imageToBase64($whiteLabel['logo_url']);
        }

        $customerLogo = null;
        if (isset($customerBranding['customer_logo_url']) && $customerBranding['customer_logo_url']) {
            $customerLogo = $this->imageToBase64($customerBranding['customer_logo_url']);
        }

        $reportData['branding'] = [
            'company_name' => $whiteLabel['company_name'] ?? $company->name,
            'company_logo' => $companyLogo,
            'customer_logo' => $customerLogo,
            'primary_color' => $whiteLabel['primary_color'] ?? '#1a56db',
            'secondary_color' => $whiteLabel['secondary_color'] ?? '#7c3aed',
            'report_title' => $customerBranding['report_title'] ?? ($whiteLabel['report_header_text'] ?? 'Monthly Service Report'),
            'footer_text' => $whiteLabel['footer_text'] ?? '',
            'footer_notes' => $customerBranding['footer_notes'] ?? '',
            'support_email' => $whiteLabel['support_email'] ?? null,
            'support_phone' => $whiteLabel['support_phone'] ?? null,
        ];

        return $reportData;
    }

    private function generatePDF(array $reportData, ReportSchedule $schedule): string
    {
        // Generate filename using naming service (respects company naming pattern)
        // Pass customer explicitly to ensure tokens are populated correctly
        $filename = $this->namingService->generateFilename($schedule, $schedule->company, $schedule->customer);

        // Ensure base reports directory exists with proper permissions
        $reportsBasePath = Storage::disk('reports')->path('');
        if (!file_exists($reportsBasePath)) {
            if (!mkdir($reportsBasePath, 0775, true)) {
                Log::error('Failed to create base reports directory', [
                    'path' => $reportsBasePath,
                ]);
                throw new \Exception("Failed to create base reports directory: {$reportsBasePath}");
            }
            @chmod($reportsBasePath, 0775);
            @chown($reportsBasePath, 'www');
            @chgrp($reportsBasePath, 'www');
        }

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

            // Ensure proper ownership and permissions (www user in container)
            @chmod($fullDirPath, 0775);
            @chown($fullDirPath, 'www');
            @chgrp($fullDirPath, 'www');

            Log::info('Created report directory', [
                'directory' => $directory,
                'full_path' => $fullDirPath,
                'permissions' => decoct(fileperms($fullDirPath) & 0777),
            ]);
        }

        $html = $this->generateHTML($reportData, $schedule);

        // Generate header and footer HTML for repeating on every page
        $headerHtml = $this->generatePDFHeader($reportData, $schedule);
        $footerHtml = $this->generatePDFFooter($reportData, $schedule);

        // Use Gotenberg to generate PDF with repeating header/footer
        $pdfContent = $this->gotenberg->htmlToPdf($html, [
            'paper_width' => '8.27',  // A4 width in inches
            'paper_height' => '11.69',  // A4 height in inches
            'margin_top' => '1.0',  // 1.0 inch (~25mm) top margin for header
            'margin_bottom' => '0.8',  // 0.8 inch (~20mm) bottom margin for footer
            'margin_left' => '0.4',  // 0.4 inch (~10mm) left margin
            'margin_right' => '0.4',  // 0.4 inch (~10mm) right margin
            'header_html' => $headerHtml,
            'footer_html' => $footerHtml,
            'print_background' => true,
            'prefer_css_page_size' => false,
            'wait_delay' => '2s',  // Wait 2 seconds for images to load
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
        @chmod($fullPath, 0664);
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
            ]);

            // Try to render using Blade view name (dot notation)
            try {
                return view($templatePath, [
                    'reportData' => $reportData,
                    'schedule' => $schedule,
                    'customer' => $schedule->customer,
                    'options' => $reportData['options'] ?? [],  // Pass options for dynamic title
                    'branding' => $reportData['branding'] ?? [],
                    'metrics' => $reportData['metrics'] ?? [],
                    'period' => $reportData['period'] ?? [],
                    'data' => $reportData['data'] ?? [],
                    'alerts' => $reportData['alerts'] ?? [],
                    'certificates' => $reportData['certificates'] ?? [],
                    'licenses' => $reportData['licenses'] ?? [],
                    'ssl_certificates' => $reportData['ssl_certificates'] ?? [],
                    'domains' => $reportData['domains'] ?? [],
                    'all_alerts' => $reportData['all_alerts'] ?? [],
                    'summary' => $reportData['summary'] ?? [],
                    'recommendations' => $reportData['recommendations'] ?? [],
                    'generated_at' => $reportData['generated_at'] ?? now()->toDateTimeString(),
                    'included_reports' => $reportData['included_reports'] ?? [],
                    'sections_to_render' => $reportData['sections_to_render'] ?? null,  // For section filtering
                    // Autotask Monthly Summary specific variables
                    'executive_summary' => $reportData['executive_summary'] ?? [],
                    'tickets' => $reportData['tickets'] ?? [],
                    'time_entries' => $reportData['time_entries'] ?? [],
                    'technicians' => $reportData['technicians'] ?? [],
                    'contracts' => $reportData['contracts'] ?? [],
                    // Sendmarc Monthly Summary specific variables
                    'top_senders' => $reportData['top_senders'] ?? [],
                    'ip_sources' => $reportData['ip_sources'] ?? [],
                    'totals' => $reportData['totals'] ?? [],
                    'compliance' => $reportData['compliance'] ?? [],
                    // BitDefender Monthly Summary specific variables
                    'endpoint_summary' => $reportData['endpoint_summary'] ?? [],
                    'policy_summary' => $reportData['policy_summary'] ?? [],
                    'os_summary' => $reportData['os_summary'] ?? [],
                    'threat_summary' => $reportData['threat_summary'] ?? [],
                    'endpoints_list' => $reportData['endpoints_list'] ?? [],
                    'policy_details' => $reportData['policy_details'] ?? [],
                    'os_distribution' => $reportData['os_distribution'] ?? [],
                    // Datto RMM Monthly Summary specific variables
                    'device_summary' => $reportData['device_summary'] ?? [],
                    'alert_summary' => $reportData['alert_summary'] ?? [],
                    'compliance_summary' => $reportData['compliance_summary'] ?? [],
                    'security_summary' => $reportData['security_summary'] ?? [],
                    'devices' => $reportData['devices'] ?? [],
                    'uptime_metrics' => $reportData['uptime_metrics'] ?? [],
                    // SaaS Monthly Summary specific variables
                    'user_activity' => $reportData['user_activity'] ?? [],
                    'geographic_data' => $reportData['geographic_data'] ?? [],
                    'application_usage' => $reportData['application_usage'] ?? [],
                    'threat_statistics' => $reportData['threat_statistics'] ?? [],
                    'device_statistics' => $reportData['device_statistics'] ?? [],
                    'date_range' => $reportData['date_range'] ?? [],
                    // LibreNMS Device Report
                    'groups' => $reportData['groups'] ?? [],
                ])->render();
            } catch (\Exception $e) {
                Log::error('Failed to render Blade template, trying file path', [
                    'template_path' => $templatePath,
                    'error' => $e->getMessage()
                ]);

                // Fallback: try as file path if Blade view name doesn't work
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
                        'certificates' => $reportData['certificates'] ?? [],
                        'licenses' => $reportData['licenses'] ?? [],
                        'ssl_certificates' => $reportData['ssl_certificates'] ?? [],
                        'domains' => $reportData['domains'] ?? [],
                        'all_alerts' => $reportData['all_alerts'] ?? [],
                        'summary' => $reportData['summary'] ?? [],
                        'recommendations' => $reportData['recommendations'] ?? [],
                        'generated_at' => $reportData['generated_at'] ?? now()->toDateTimeString(),
                        'included_reports' => $reportData['included_reports'] ?? [],
                        // Autotask Monthly Summary specific variables
                        'executive_summary' => $reportData['executive_summary'] ?? [],
                        'tickets' => $reportData['tickets'] ?? [],
                        'time_entries' => $reportData['time_entries'] ?? [],
                        'technicians' => $reportData['technicians'] ?? [],
                        'contracts' => $reportData['contracts'] ?? [],
                        // Sendmarc Monthly Summary specific variables
                        'top_senders' => $reportData['top_senders'] ?? [],
                        'ip_sources' => $reportData['ip_sources'] ?? [],
                        'totals' => $reportData['totals'] ?? [],
                        'compliance' => $reportData['compliance'] ?? [],
                        // BitDefender Monthly Summary specific variables
                        'endpoint_summary' => $reportData['endpoint_summary'] ?? [],
                        'policy_summary' => $reportData['policy_summary'] ?? [],
                        'os_summary' => $reportData['os_summary'] ?? [],
                        'threat_summary' => $reportData['threat_summary'] ?? [],
                        'endpoints_list' => $reportData['endpoints_list'] ?? [],
                        'policy_details' => $reportData['policy_details'] ?? [],
                        'os_distribution' => $reportData['os_distribution'] ?? [],
                        // Datto RMM Monthly Summary specific variables
                        'device_summary' => $reportData['device_summary'] ?? [],
                        'alert_summary' => $reportData['alert_summary'] ?? [],
                        'compliance_summary' => $reportData['compliance_summary'] ?? [],
                        'security_summary' => $reportData['security_summary'] ?? [],
                        'devices' => $reportData['devices'] ?? [],
                        'uptime_metrics' => $reportData['uptime_metrics'] ?? [],
                        // SaaS Monthly Summary specific variables
                        'user_activity' => $reportData['user_activity'] ?? [],
                        'geographic_data' => $reportData['geographic_data'] ?? [],
                        'application_usage' => $reportData['application_usage'] ?? [],
                        'threat_statistics' => $reportData['threat_statistics'] ?? [],
                        'device_statistics' => $reportData['device_statistics'] ?? [],
                        'date_range' => $reportData['date_range'] ?? [],
                        // LibreNMS Device Report
                        'groups' => $reportData['groups'] ?? [],
                    ])->render();
                }
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

    /**
     * Convert image URL or path to base64 data URI for PDF embedding
     *
     * @param string $url Image URL or storage path
     * @return string|null Base64 data URI or null on failure
     */
    private function imageToBase64(string $url): ?string
    {
        $cacheKey = 'logo_base64_' . md5($url);

        return Cache::remember($cacheKey, 3600, function () use ($url) {
            try {
                $content = null;
                $mimeType = null;

                // Check if the URL is a relative storage path
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    // Try public disk first (for logos)
                    if (Storage::disk('public')->exists($url)) {
                        $content = Storage::disk('public')->get($url);
                        $mimeType = Storage::disk('public')->mimeType($url);
                    }
                    // Fallback to default disk
                    elseif (Storage::exists($url)) {
                        $content = Storage::get($url);
                        $mimeType = Storage::mimeType($url);
                    } else {
                        Log::warning('Logo file not found in storage', ['path' => $url]);
                        return null;
                    }
                }
                // It's a full URL
                else {
                    $content = @file_get_contents($url);
                    if ($content === false) {
                        Log::warning('Failed to fetch logo from URL', ['url' => $url]);
                        return null;
                    }

                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($content);
                }

                if (!$content) {
                    return null;
                }

                // Validate it's an image
                if (!str_starts_with($mimeType, 'image/')) {
                    Log::warning('File is not an image', [
                        'url' => $url,
                        'mime_type' => $mimeType,
                    ]);
                    return null;
                }

                return 'data:' . $mimeType . ';base64,' . base64_encode($content);
            } catch (\Exception $e) {
                Log::error('Failed to convert image to base64', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Generate PDF header HTML for repeating on every page
     */
    private function generatePDFHeader(array $reportData, ReportSchedule $schedule): string
    {
        $branding = $reportData['branding'] ?? [];
        $customer = $schedule->customer;

        return view('reports.partials.pdf-header', [
            'branding' => $branding,
            'customer' => $customer,
        ])->render();
    }

    /**
     * Generate PDF footer HTML for repeating on every page with page numbers
     */
    private function generatePDFFooter(array $reportData, ReportSchedule $schedule): string
    {
        $branding = $reportData['branding'] ?? [];

        return view('reports.partials.pdf-footer', [
            'branding' => $branding,
        ])->render();
    }
}
