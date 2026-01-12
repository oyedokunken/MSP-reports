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
use App\Services\MailService;
use App\Services\Delivery\DeliveryManager;

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

    public function handle(DeliveryManager $deliveryManager): void
    {
        $deliveryMethods = [];
        
        try {
            // Small delay to ensure file system has synced (especially important for NFS/network storage)
            usleep(100000); // 100ms delay
            
            // CRITICAL: Refresh the model from database to get the latest file_path
            // The model might have been serialized before file_path was set
            $this->generatedReport = $this->generatedReport->fresh();
            
            if (!$this->generatedReport) {
                throw new \Exception("Generated report not found in database");
            }
            
            // Clear file stat cache to ensure fresh file info
            if ($this->generatedReport->file_path) {
                $fullPath = Storage::disk('reports')->path($this->generatedReport->file_path);
                clearstatcache(true, $fullPath);
                
                Log::info('DeliverReportJob file check', [
                    'report_id' => $this->generatedReport->generated_report_id,
                    'file_path' => $this->generatedReport->file_path,
                    'full_path' => $fullPath,
                    'file_exists' => file_exists($fullPath),
                    'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                    'file_readable' => is_readable($fullPath),
                ]);
            }
            
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
