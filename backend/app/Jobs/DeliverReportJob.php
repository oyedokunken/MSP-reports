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

class DeliverReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected GeneratedReport $generatedReport;

    /**
     * Create a new job instance.
     */
    public function __construct(GeneratedReport $generatedReport)
    {
        $this->generatedReport = $generatedReport;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $customer = $this->generatedReport->customer;
            $company = $this->generatedReport->company;

            // Get delivery destination
            $destination = $customer->report_destination 
                ?? $customer->report_configuration['delivery']['destination'] 
                ?? null;

            if (!$destination) {
                throw new \Exception("No delivery destination configured for customer.");
            }

            // Get delivery method
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

            // Update delivery status
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

            $this->generatedReport->update([
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Deliver report via email.
     */
    private function deliverViaEmail(string $destination, $company): void
    {
        // TODO: Implement actual email delivery
        // For now, just log
        Log::info('Email delivery (placeholder)', [
            'to' => $destination,
            'report_id' => $this->generatedReport->generated_report_id,
        ]);

        // Actual implementation would use Mail facade:
        // Mail::to($destination)->send(new ReportDeliveryMail($this->generatedReport, $company));
    }

    /**
     * Deliver report via API webhook.
     */
    private function deliverViaAPI(string $webhookUrl): void
    {
        // TODO: Implement API webhook delivery
        Log::info('API delivery (placeholder)', [
            'webhook_url' => $webhookUrl,
            'report_id' => $this->generatedReport->generated_report_id,
        ]);

        // Actual implementation would use HTTP client:
        // Http::post($webhookUrl, [
        //     'report_id' => $this->generatedReport->generated_report_id,
        //     'file_url' => Storage::url($this->generatedReport->file_path),
        // ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report delivery job failed permanently', [
            'generated_report_id' => $this->generatedReport->generated_report_id,
            'error' => $exception->getMessage(),
        ]);

        $this->generatedReport->update([
            'delivered' => false,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
