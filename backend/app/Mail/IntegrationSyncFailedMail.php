<?php

namespace App\Mail;

use App\Models\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IntegrationSyncFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Integration $integration;
    public \Throwable $exception;
    public int $attempts;

    public function __construct(Integration $integration, \Throwable $exception, int $attempts = 3)
    {
        $this->integration = $integration;
        $this->exception = $exception;
        $this->attempts = $attempts;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 CRITICAL: Integration Sync Failed - {$this->integration->display_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.integration-sync-failed',
            with: [
                'integration' => $this->integration,
                'exception' => $this->exception,
                'attempts' => $this->attempts,
                'companyName' => $this->integration->company->name,
            ],
        );
    }
}
