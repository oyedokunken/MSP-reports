<?php

namespace App\Services\Delivery\Drivers;

use App\Models\GeneratedReport;
use App\Models\Customer;
use App\Models\Company;
use App\Services\Delivery\Contracts\DeliveryDriverInterface;
use App\Services\MailService;
use App\Mail\ReportDeliveryMail;
use Illuminate\Support\Facades\Log;

class EmailDriver implements DeliveryDriverInterface
{
    public function getIdentifier(): string
    {
        return 'email';
    }

    public function getDisplayName(): string
    {
        return 'Email';
    }

    /**
     * @param GeneratedReport $report
     * @param array<string, mixed> $config
     * @param Company $company
     * @param Customer $customer
     * @return array{success: bool, destination: string, message?: string}
     */
    public function deliver(GeneratedReport $report, array $config, Company $company, Customer $customer): array
    {
        // Handle override recipients (for manual sends)
        if (isset($config['override_recipients']) && !empty($config['override_recipients'])) {
            $destination = $config['override_recipients'][0];
            $ccAddresses = array_slice($config['override_recipients'], 1);
        } else {
            $destination = $config['destination'] 
                ?? $customer->report_destination 
                ?? null;
            $ccAddresses = $config['cc'] ?? [];
        }

        if (!$destination) {
            throw new \Exception("No email destination configured for customer.");
        }

        $mailService = resolve(MailService::class);
        $mailer = $mailService->getMailer($company);
        
        $mail = $mailer->to($destination);
        
        if (!empty($ccAddresses)) {
            $mail->cc($ccAddresses);
        }

        $mail->send(new ReportDeliveryMail($report, $company));

        Log::info('Report delivered via email', [
            'to' => $destination,
            'cc' => $ccAddresses,
            'report_id' => $report->generated_report_id,
        ]);

        return [
            'success' => true,
            'message' => "Report sent to {$destination}",
            'destination' => $destination,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['destination'])) {
            $errors[] = 'Email destination is required';
        } elseif (!filter_var($config['destination'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address format';
        }

        if (!empty($config['cc'])) {
            foreach ($config['cc'] as $cc) {
                if (!filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid CC email address: {$cc}";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'destination',
                    'label' => 'Email Address',
                    'type' => 'email',
                    'required' => true,
                    'placeholder' => 'reports@customer.com',
                ],
                [
                    'name' => 'cc',
                    'label' => 'CC Addresses',
                    'type' => 'email_list',
                    'required' => false,
                    'placeholder' => 'Add CC recipients',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param Company $company
     * @return array{success: bool, message: string}
     */
    public function testConnection(array $config, Company $company): array
    {
        $validation = $this->validateConfig($config);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid configuration: ' . implode(', ', $validation['errors']),
            ];
        }

        // For email, we just validate the config - actual send test would require sending an email
        return [
            'success' => true,
            'message' => 'Email configuration is valid',
        ];
    }
}
