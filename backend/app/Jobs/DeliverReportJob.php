<?php

declare(strict_types=1);

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
use App\Services\MailService;
use App\Services\Delivery\DeliveryManager;
use App\Services\NotificationService;

final class DeliverReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    protected GeneratedReport $generatedReport;
    /** @var array<int, string>|null */
    protected ?array $customRecipients;

    /**
     * @param array<int, string>|null $customRecipients
     */
    public function __construct(GeneratedReport $generatedReport, ?array $customRecipients = null)
    {
        $this->generatedReport = $generatedReport;
        $this->customRecipients = $customRecipients;
    }

    public function handle(DeliveryManager $deliveryManager): void
    {
        $deliveryMethods = [];
        
        try {
            // Eager load relationships to ensure they're available
            $this->generatedReport->load(['customer', 'reportSchedule', 'company']);
            
            $customer = $this->generatedReport->customer;
            $company = $this->generatedReport->company;

            // Use the new DeliveryManager for extensible delivery
            $results = $deliveryManager->deliver(
                $this->generatedReport,
                $customer,
                $company,
                $this->customRecipients
            );

            // Check if any delivery method succeeded
            $anySuccess = false;
            $failedMethods = [];
            $successMethods = [];
            
            foreach ($results as $method => $result) {
                $deliveryMethods[] = $method;
                if ($result['success']) {
                    $anySuccess = true;
                    $successMethods[] = $method;
                } else {
                    $failedMethods[$method] = $result['message'] ?? 'Unknown error';
                }
            }

            if (!$anySuccess) {
                $errorMessages = array_map(
                    fn($m, $e) => "{$m}: {$e}",
                    array_keys($failedMethods),
                    array_values($failedMethods)
                );
                throw new \Exception("All delivery methods failed: " . implode('; ', $errorMessages));
            }

            $this->generatedReport->update([
                'delivered' => true,
                'delivered_at' => now(),
                'delivery_method' => implode(',', $successMethods),
            ]);

            Log::info('Report delivered successfully', [
                'generated_report_id' => $this->generatedReport->generated_report_id,
                'delivery_methods' => $successMethods,
                'failed_methods' => array_keys($failedMethods),
            ]);

            // Send delivery confirmation notification
            try {
                $notificationService = resolve(NotificationService::class);
                $notificationService->sendReportDeliveryConfirmation($this->generatedReport, $results);
            } catch (\Exception $e) {
                Log::warning('Failed to send delivery confirmation notification', [
                    'report_id' => $this->generatedReport->generated_report_id,
                    'error' => $e->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Report delivery failed', [
                'generated_report_id' => $this->generatedReport->generated_report_id,
                'error' => $e->getMessage(),
            ]);

            // Create user-friendly error message
            $primaryMethod = $deliveryMethods[0] ?? 'email';
            $errorMessage = $this->formatErrorMessage($e->getMessage(), $primaryMethod);

            $this->generatedReport->update([
                'error_message' => $errorMessage,
            ]);

            throw $e;
        }
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
        if (str_contains($rawError, 'No delivery destination') || str_contains($rawError, 'No email destination')) {
            return 'Delivery failed: No recipient configured';
        }
        
        // Check for SharePoint errors
        if (str_contains($rawError, 'SharePoint')) {
            return "SharePoint delivery failed: {$rawError}";
        }
        
        // Default message for other errors
        return "Delivery failed: {$rawError}";
    }
}
