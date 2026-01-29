<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Get a mailer instance configured for a specific company
     *
     * @param Company $company
     * @return \Illuminate\Contracts\Mail\Mailer
     */
    public function getMailer(Company $company)
    {
        $smtp = $company->settings['mail_settings']['smtp'] ?? null;

        if (!$smtp || empty($smtp['host'])) {
            return Mail::mailer();
        }

        try {
            // Configure a one-time custom mailer for this request
            $config = [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => $smtp['port'] ?? 587,
                'encryption' => $smtp['encryption'] ?? 'tls',
                'username' => $smtp['username'] ?? null,
                'password' => $smtp['password'] ?? null,
                'timeout' => null,
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ];

            // Use 'build' if available (Laravel 10+) or dynamically set config
            // For stability across different Laravel versions, we use dynamic config injection
            Config::set('mail.mailers.tenant_smtp', $config);

            return Mail::mailer('tenant_smtp');
        } catch (\Exception $e) {
            Log::error('Failed to configure tenant SMTP, falling back to default', [
                'company_id' => $company->company_id,
                'error' => $e->getMessage(),
            ]);
            return Mail::mailer();
        }
    }

    /**
     * Send a mailable using the tenant's mailer
     *
     * @param Company $company
     * @param string|array<int, string> $to
     * @param \Illuminate\Contracts\Mail\Mailable $mailable
     * @return void
     */
    public function send(Company $company, $to, $mailable)
    {
        $mailer = $this->getMailer($company);
        $mailer->to($to)->send($mailable);
    }
}
