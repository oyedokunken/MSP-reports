<?php

namespace App\Mail;

use App\Models\AlertLog;
use App\Models\AlertRule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public AlertLog $alertLog;
    public AlertRule $alertRule;

    public function __construct(AlertLog $alertLog, AlertRule $alertRule)
    {
        $this->alertLog = $alertLog;
        $this->alertRule = $alertRule;
    }

    public function envelope(): Envelope
    {
        $severityPrefix = match ($this->alertLog->severity) {
            'critical' => '🚨 CRITICAL',
            'warning' => '⚠️ WARNING',
            'info' => 'ℹ️ INFO',
            default => 'ALERT',
        };

        return new Envelope(
            subject: "{$severityPrefix}: {$this->alertLog->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-notification',
            with: [
                'alertLog' => $this->alertLog,
                'alertRule' => $this->alertRule,
                'companyName' => $this->alertRule->company->name,
            ],
        );
    }
}
