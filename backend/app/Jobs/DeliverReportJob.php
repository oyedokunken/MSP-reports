<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReportDeliveryMail;

class DeliverReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected GeneratedReport $generatedReport;
    protected ?array $customRecipients;

    public function __construct(GeneratedReport $generatedReport, ?array $customRecipients = null)
    {
        $this->generatedReport = $generatedReport;
        $this->customRecipients = $customRecipients;
    }

    public function handle(): void
    {
        try {
            // Eager load relationships to ensure they're available
            $this->generatedReport->load(['customer', 'reportSchedule', 'company']);
            
            $customer = $this->generatedReport->customer;
            $company = $this->generatedReport->company;

            $destination = $customer->report_destination 
                ?? $customer->report_configuration['delivery']['destination'] 
                ?? null;

            if (!$destination) {
                throw new \Exception("No delivery destination configured for customer.");
            }

            $deliveryMethod = $customer->report_configuration['delivery']['method'] ?? 'email';

            switch ($deliveryMethod) {
                case 'email':
                    $this->deliverViaEmail($destination, $company);
                    break;

                case 'api':
                    $this->deliverViaAPI($destination);
                    break;

                default:
                    throw new \Exception("Unsupported delivery method: {$deliveryMethod}");
            }

            $this->generatedReport->update([
                'delivered' => true,
                'delivered_at' => now(),
                'delivery_method' => $deliveryMethod,
            ]);

            Log::info('Report delivered successfully', [
                'generated_report_id' => $this->generatedReport->generated_report_id,
                'delivery_method' => $deliveryMethod,
            ]);

        } catch (\Exception $e) {
            Log::error('Report delivery failed', [
                'generated_report_id' => $this->generatedReport->generated_report_id,
                'error' => $e->getMessage(),
            ]);

            // Create user-friendly error message
            $errorMessage = $this->formatErrorMessage($e->getMessage(), $deliveryMethod ?? 'email');

            $this->generatedReport->update([
                'error_message' => $errorMessage,
            ]);

            throw $e;
        }
    }

    private function deliverViaEmail(string $destination, $company, ?array $additionalRecipients = null): void
    {
        $customer = $this->generatedReport->customer;
        
        // If additional recipients provided (from custom email), use them
        // Otherwise, use configured CC addresses
        $ccAddresses = $additionalRecipients ?? ($customer->report_configuration['delivery']['cc'] ?? []);

        $mail = Mail::to($destination);
        
        if (!empty($ccAddresses)) {
            $mail->cc($ccAddresses);
        }

        $mail->send(new ReportDeliveryMail($this->generatedReport, $company));

        Log::info('Report delivered via email', [
            'to' => $destination,
            'cc' => $ccAddresses,
            'report_id' => $this->generatedReport->generated_report_id,
        ]);
    }

    private function deliverViaAPI(string $webhookUrl): void
    { 
        Log::info('API delivery (placeholder)', [
            'webhook_url' => $webhookUrl,
            'report_id' => $this->generatedReport->generated_report_id,
        ]);  
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report delivery job failed permanently', [
            'generated_report_id' => $this->generatedReport->generated_report_id,
            'error' => $exception->getMessage(),
        ]);

        // Create user-friendly error message
        $errorMessage = $this->formatErrorMessage($exception->getMessage(), 'email');

        $this->generatedReport->update([
            'delivered' => false,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Format error message to be user-friendly
     */
    private function formatErrorMessage(string $rawError, string $deliveryMethod): string
    {
        // Check for common SMTP errors
        if (str_contains($rawError, 'Expected response code')) {
            // Extract the actual SMTP error if possible
            if (preg_match('/code "(\d+)".*?message "(.*?)"/', $rawError, $matches)) {
                $code = $matches[1];
                $message = $matches[2];
                
                // Map common SMTP errors to user-friendly messages
                $friendlyMessages = [
                    '530' => 'Email delivery failed: SMTP authentication or sender configuration error',
                    '550' => 'Email delivery failed: Recipient address rejected',
                    '551' => 'Email delivery failed: User not local',
                    '552' => 'Email delivery failed: Mailbox full',
                    '553' => 'Email delivery failed: Invalid email address',
                    '554' => 'Email delivery failed: Transaction failed',
                ];
                
                return $friendlyMessages[$code] ?? "Email delivery failed: SMTP error {$code} - {$message}";
            }
            
            return 'Email delivery failed: SMTP server error';
        }
        
        // Check for authentication errors
        if (str_contains($rawError, 'authentication') || str_contains($rawError, 'auth')) {
            return 'Email delivery failed: SMTP authentication error';
        }
        
        // Check for connection errors
        if (str_contains($rawError, 'Connection') || str_contains($rawError, 'connect')) {
            return 'Email delivery failed: Could not connect to mail server';
        }
        
        // Check for no destination
        if (str_contains($rawError, 'No delivery destination')) {
            return 'Email delivery failed: No recipient email configured';
        }
        
        // Default message for other errors
        return "Email delivery failed: {$rawError}";
    }
}
