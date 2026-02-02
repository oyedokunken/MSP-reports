<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

final class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $plainPassword;
    public string $loginUrl;

    public function __construct(User $user, string $plainPassword)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->loginUrl = "{$frontendUrl}/login";
    }

    public function envelope(): Envelope
    {
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $fromName),
            subject: 'Welcome to the Platform - Your Account Has Been Created',
        );
    }

    public function content(): Content
    {
        $company = $this->user->company;
        $whiteLabelSettings = $company->settings['white_label'] ?? [];

        return new Content(
            view: 'emails.welcome-user',
            with: [
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => $this->loginUrl,
                'companyName' => $whiteLabelSettings['company_name'] ?? 'MSP Reporting Platform',
                'supportEmail' => $whiteLabelSettings['support_email'] ?? config('mail.from.address'),
            ],
        );
    }
}
