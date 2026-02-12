<?php

namespace App\Console\Commands\TestReports;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Services\ReportGenerationService;
use App\Mail\ReportDeliveryMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TestDattoReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-datto-report {email : The email to send the report to} {--customer=18 : Customer ID} {--months=1 : How many months back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send a test Datto RMM Summary report PDF';

    /**
     * Execute the console command.
     */
    public function handle(ReportGenerationService $reportService)
    {
        $email = $this->argument('email');
        $customerId = $this->option('customer');
        $months = (int)$this->option('months');

        $this->info("Starting report generation for customer ID: {$customerId}");

        $customer = Customer::withoutGlobalScopes()->find($customerId);
        if (!$customer) {
            $this->error("Customer not found.");
            return 1;
        }

        $this->info("Customer: {$customer->canonical_name}");

        // Find or create a schedule for testing
        $schedule = ReportSchedule::withoutGlobalScopes()->where('customer_id', $customerId)
            ->where('report_type', 'datto_rmm_summary')
            ->first();

        if (!$schedule) {
            $this->warn("No existing Datto RMM Summary schedule found for customer {$customerId}. Creating a temporary one.");
            $schedule = new ReportSchedule([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->customer_id,
                'report_type' => 'datto_rmm_summary',
                'frequency' => 'monthly',
                'status' => 'active',
                'schedule_config' => [
                    'included_sections' => [
                        'executive_summary',
                        'device_summary',
                        'alert_summary',
                        'compliance_summary',
                        'security_summary',
                        'uptime_metrics',
                        'recommendations'
                    ]
                ]
            ]);
            // Save it because ReportGenerationService might need it to exist in DB
            $schedule->save();
            $this->info("Temporary schedule created with ID: {$schedule->report_schedule_id}");
        } else {
            $this->info("Using existing schedule ID: {$schedule->report_schedule_id}");
        }

        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

        $this->info("Generating report for period: {$startDate->toDateString()} to {$endDate->toDateString()}");

        try {
            // Include data types from config in the log/info
            $dattoConfig = include(app_path('Integrations/Datto/config.php'));
            $dataTypes = $dattoConfig['data_types'] ?? [];
            $this->info("Integrated Datto Data Types: " . implode(', ', array_keys($dataTypes)));

            $generatedReport = $reportService->generateReport($schedule, $startDate, $endDate);
            
            $this->info("Report generated successfully!");
            $this->info("Status: {$generatedReport->status}");
            $this->info("File: {$generatedReport->file_path}");

            if ($generatedReport->status === 'completed' && $generatedReport->file_path) {
                $this->info("Sending email to: {$email}");
                
                Mail::to($email)->send(new ReportDeliveryMail($generatedReport, $customer->company));

                $this->info("Email sent successfully!");
            } else {
                $this->error("Report was not completed successfully. Status: {$generatedReport->status}");
                if ($generatedReport->error_message) {
                    $this->error("Error: {$generatedReport->error_message}");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Error during process: " . $e->getMessage());
            Log::error("TestDattoReport failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
