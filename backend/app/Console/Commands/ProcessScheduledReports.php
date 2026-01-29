<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReportScheduleService;
use Illuminate\Console\Command;

class ProcessScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process-scheduled 
                            {--force : Force processing even if not due}
                            {--schedule= : Process specific schedule ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due report schedules and generate reports';

    /**
     * Execute the console command.
     */
    public function handle(ReportScheduleService $scheduleService): int
    {
        $this->info('Processing scheduled reports...');

        try {
            // Process specific schedule if provided
            if ($scheduleId = $this->option('schedule')) {
                return $this->processSpecificSchedule($scheduleService, (int)$scheduleId);
            }

            // Process all due schedules
            $results = $scheduleService->processDueSchedules();

            $this->displayResults($results);

            return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to process schedules: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Process a specific schedule
     */
    protected function processSpecificSchedule(ReportScheduleService $scheduleService, int $scheduleId): int
    {
        $schedule = \App\Models\ReportSchedule::find($scheduleId);

        if (!$schedule) {
            $this->error("Schedule #{$scheduleId} not found");
            return Command::FAILURE;
        }

        $this->info("Processing schedule #{$scheduleId}...");
        $this->line("  Report Type: {$schedule->report_type}");
        $this->line("  Customer: {$schedule->customer->canonical_name}");
        $this->line("  Frequency: {$schedule->getFrequencyLabel()}");

        try {
            $scheduleService->processSchedule($schedule);
            $this->info("✓ Schedule processed successfully");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Failed to process schedule: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Display processing results
     * @param array<string, int|array<int, array<string, mixed>>> $results
     */
    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info("Processing completed:");
        $this->line("  Processed: " . (is_array($results['processed']) ? count($results['processed']) : $results['processed']));
        $this->line("  Succeeded: " . (is_array($results['succeeded']) ? count($results['succeeded']) : $results['succeeded']));
        
        if ($results['failed'] > 0) {
            $this->error("  Failed: " . (is_array($results['failed']) ? count($results['failed']) : $results['failed']));
            
            if (isset($results['errors']) && is_iterable($results['errors'])) {
                $this->newLine();
                $this->error("Errors:");
                /** @var array<string, mixed> $error */
                foreach ($results['errors'] as $error) {
                    $this->line("  Schedule #{$error['schedule_id']} ({$error['report_type']}): {$error['error']}");
                }
            }
        }

        $this->newLine();
    }
}
