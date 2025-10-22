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

    public function __construct(GeneratedReport $generatedReport)
    {
        $this->generatedReport = $generatedReport;
    }

    public function handle(): void
    {
        try {
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

            $this->generatedReport->update([
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function deliverViaEmail(string $destination, $company): void
    {
        $customer = $this->generatedReport->customer;
        $ccAddresses = $customer->report_configuration['delivery']['cc'] ?? [];

        
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

        $this->generatedReport->update([
            'delivered' => false,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
