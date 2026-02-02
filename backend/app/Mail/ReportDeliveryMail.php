<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\GeneratedReport;
use App\Models\Company;
use App\Services\ReportNamingService;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

final class ReportDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public GeneratedReport $report;
    public Company $company;
    /** @var array<string, mixed> */
    public array $branding;

    public function __construct(GeneratedReport $report, Company $company)
    {
        $this->report = $report;
        $this->company = $company;
        $this->branding = $company->settings['white_label'] ?? [];
    }

    public function envelope(): Envelope
    {
        // Use config defaults for from address - Postal requires exact match
        $fromAddress = config('mail.from.address');
        $subject = $this->branding['report_header_text'] ?? 'Your Monthly Service Report';

        // Don't override sender name - use what's configured in .env
        // Postal validates sender names strictly
        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $period = $this->report->report_period;
        $periodString = 'N/A';
        
        if (is_array($period) && isset($period['start']) && isset($period['end'])) {
            $start = Carbon::parse($period['start'])->format('F j, Y');
            $end = Carbon::parse($period['end'])->format('F j, Y');
            $periodString = "{$start} - {$end}";
        }
        
        return new Content(
            view: 'emails.report-delivery',
            with: [
                'customerName' => $this->report->customer->canonical_name,
                'reportType' => $this->report->reportSchedule->report_type ?? 'Report',
                'reportPeriod' => $periodString,
                'generatedAt' => $this->report->generated_at->format('F j, Y'),
                'companyName' => $this->branding['company_name'] ?? $this->company->name,
                'supportEmail' => $this->branding['support_email'] ?? config('mail.from.address'),
                'supportPhone' => $this->branding['support_phone'] ?? '',
                'footerText' => $this->branding['footer_text'] ?? '',
                'primaryColor' => $this->branding['primary_color'] ?? '#1a56db',
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->report->file_path && Storage::disk('reports')->exists($this->report->file_path)) {
            $fullPath = Storage::disk('reports')->path($this->report->file_path);
            
            if (file_exists($fullPath) && is_readable($fullPath)) {
                $namingService = app(ReportNamingService::class);
                $attachmentName = 'Report.pdf';
                
                if ($this->report->reportSchedule) {
                    $attachmentName = $namingService->getDownloadName($this->report->reportSchedule, $this->company, $this->report->customer, $this->report->generated_at) . '.pdf';
                } else {
                    $attachmentName = basename($this->report->file_path);
                }

                $attachments[] = Attachment::fromPath($fullPath)
                    ->as($attachmentName)
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }
}
