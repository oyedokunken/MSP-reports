<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Company; // Added Company import

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $token;
    public string $resetUrl;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->resetUrl = "{$frontendUrl}/reset-password?token={$token}&email={$user->email}";
    }

    public function envelope(): Envelope
    {
        // Use config mail settings instead of white-label to avoid SMTP errors
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            subject: 'Password Reset Request',
        );
    }

    public function content(): Content
    {
        $company = $this->user->company;
        $whiteLabelSettings = $company->settings['white_label'] ?? [];

        return new Content(
            view: 'emails.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'companyName' => $whiteLabelSettings['company_name'] ?? 'MSP Reporting Platform',
                'supportEmail' => $whiteLabelSettings['support_email'] ?? config('mail.from.address'),
            ],
        );
    }
}
