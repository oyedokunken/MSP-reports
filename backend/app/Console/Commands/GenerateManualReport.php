<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Actions\Report\GenerateReportAction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateManualReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:generate-manual 
                            {customer_id : The ID of the customer}
                            {report_type : The type of report to generate (e.g., vonahi_summary)}
                            {--start= : Start date (YYYY-MM-DD)}
                            {--end= : End date (YYYY-MM-DD)}
                            {--no-deliver : Skip delivery jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually generate a report for a specific customer and date range';

    /**
     * Execute the console command.
     */
    public function handle(GenerateReportAction $generateReportAction): int
    {
        $customerId = $this->argument('customer_id');
        $reportType = $this->argument('report_type');
        $startDate = $this->option('start');
        $endDate = $this->option('end');
        $noDeliver = $this->option('no-deliver');

        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer ID {$customerId} not found.");
            return Command::FAILURE;
        }

        // Find existing schedule or create temp one
        $schedule = ReportSchedule::where('customer_id', $customerId)
            ->where('report_type', $reportType)
            ->first();

        if (!$schedule) {
            $this->warn("No existing schedule found for {$reportType}. Creating temporary schedule.");
            $schedule = new ReportSchedule([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->customer_id,
                'report_type' => $reportType,
                'frequency' => 'monthly',
                'active' => true,
                'timezone' => 'UTC',
            ]);
            // We must save it because GenerateReportAction uses its relationship and ID
            $schedule->save();
        }

        $options = [
            'start_date' => $startDate ?: now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => $endDate ?: now()->subMonth()->endOfMonth()->toDateString(),
            'preview_only' => $noDeliver,
        ];

        $this->info("Generating {$reportType} for {$customer->canonical_name}...");
        $this->line("Period: {$options['start_date']} to {$options['end_date']}");

        try {
            $report = $generateReportAction->execute($schedule, $options);
            
            $this->info("✓ Report generated successfully!");
            $this->line("Report ID: {$report->generated_report_id}");
            $this->line("Status: {$report->status}");
            
            if ($report->file_path) {
                $fullPath = \Illuminate\Support\Facades\Storage::disk('reports')->path($report->file_path);
                $this->info("File Location: {$fullPath}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to generate report: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
