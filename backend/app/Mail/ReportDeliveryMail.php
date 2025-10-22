<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\GeneratedReport;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

class ReportDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public GeneratedReport $report;
    public Company $company;
    public array $branding;

    public function __construct(GeneratedReport $report, Company $company)
    {
        $this->report = $report;
        $this->company = $company;
        $this->branding = $company->settings['white_label'] ?? [];
    }

    public function envelope(): Envelope
    {
        $fromAddress = $this->branding['email_from_address'] ?? config('mail.from.address');
        $subject = $this->branding['report_header_text'] ?? 'Your Monthly Service Report';

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $period = $this->report->report_period;
        $periodString = 'N/A';
        
        if (is_array($period) && isset($period['start']) && isset($period['end'])) {
            $start = \Carbon\Carbon::parse($period['start'])->format('F j, Y');
            $end = \Carbon\Carbon::parse($period['end'])->format('F j, Y');
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

    public function attachments(): array
    {
        $attachments = [];

        if ($this->report->file_path && Storage::exists($this->report->file_path)) {
            $attachments[] = Attachment::fromStorage($this->report->file_path)
                ->as(sprintf(
                    '%s_Report_%s.pdf',
                    str_replace(' ', '_', $this->report->customer->canonical_name),
                    $this->report->generated_at->format('Y-m-d')
                ))
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
