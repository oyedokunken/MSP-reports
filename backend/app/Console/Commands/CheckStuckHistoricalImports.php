<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HistoricalImportJob;
use App\Models\Integration;
use App\Jobs\ImportHistoricalDataJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Check for stuck historical imports and attempt to restart them.
 * This handles cases where:
 * - Queue worker crashed mid-import
 * - Job timed out but wasn't properly marked as failed
 * - Import is stuck in 'running' status for too long
 */
class CheckStuckHistoricalImports extends Command
{
    protected $signature = 'imports:check-stuck {--fix : Attempt to fix stuck imports}';
    protected $description = 'Check for stuck historical imports and optionally restart them';

    public function handle(): int
    {
        $this->info('Checking for stuck historical imports...');

        // Find imports stuck in 'running' status for more than 2 hours
        $stuckRunning = HistoricalImportJob::where('status', 'running')
            ->where('updated_at', '<', now()->subHours(2))
            ->get();

        // Find imports stuck in 'queued' status for more than 1 hour
        $stuckQueued = HistoricalImportJob::where('status', 'queued')
            ->where('created_at', '<', now()->subHour())
            ->get();

        // Find integrations stuck in 'importing_historical' with no active import jobs
        $stuckIntegrations = Integration::where('status', 'importing_historical')
            ->whereDoesntHave('historicalImportJobs', function ($query) {
                $query->whereIn('status', ['queued', 'running']);
            })
            ->get();

        $this->info("Found {$stuckRunning->count()} imports stuck in 'running' status");
        $this->info("Found {$stuckQueued->count()} imports stuck in 'queued' status");
        $this->info("Found {$stuckIntegrations->count()} integrations stuck in 'importing_historical' with no active jobs");

        if ($stuckRunning->isEmpty() && $stuckQueued->isEmpty() && $stuckIntegrations->isEmpty()) {
            $this->info('No stuck imports found.');
            return 0;
        }

        // Display stuck imports
        foreach ($stuckRunning as $job) {
            $this->warn("Stuck running: Job #{$job->historical_import_job_id} for integration {$job->integration_id} - last updated {$job->updated_at}");
        }

        foreach ($stuckQueued as $job) {
            $this->warn("Stuck queued: Job #{$job->historical_import_job_id} for integration {$job->integration_id} - created {$job->created_at}");
        }

        foreach ($stuckIntegrations as $integration) {
            $this->warn("Stuck integration: {$integration->display_name} (#{$integration->integration_id}) - status is 'importing_historical' but no active jobs");
        }

        if (!$this->option('fix')) {
            $this->info('Run with --fix to attempt to restart stuck imports');
            return 0;
        }

        $this->info('Attempting to fix stuck imports...');

        // Fix stuck running jobs - mark as failed and let user retry
        foreach ($stuckRunning as $job) {
            $job->update([
                'status' => 'failed',
                'error_count' => $job->error_count + 1,
            ]);

            Log::warning('Marked stuck running import as failed', [
                'import_job_id' => $job->historical_import_job_id,
                'integration_id' => $job->integration_id,
            ]);

            $this->info("Marked job #{$job->historical_import_job_id} as failed");
        }

        // Fix stuck queued jobs - re-dispatch them
        foreach ($stuckQueued as $job) {
            try {
                // Re-dispatch the job
                $chunkDate = Carbon::parse($job->current_position ?? $job->import_start_date);
                ImportHistoricalDataJob::dispatch($job, $chunkDate);

                $this->info("Re-dispatched job #{$job->historical_import_job_id}");

                Log::info('Re-dispatched stuck queued import', [
                    'import_job_id' => $job->historical_import_job_id,
                    'integration_id' => $job->integration_id,
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to re-dispatch job #{$job->historical_import_job_id}: {$e->getMessage()}");
            }
        }

        // Fix stuck integrations - reset status to active
        foreach ($stuckIntegrations as $integration) {
            // Check if there are any completed or failed import jobs
            $lastJob = $integration->historicalImportJobs()
                ->orderBy('created_at', 'desc')
                ->first();

            $newStatus = 'active';
            $historicalStatus = $lastJob?->status === 'completed' ? 'completed' : 'failed';

            $integration->update([
                'status' => $newStatus,
                'historical_import_status' => $historicalStatus,
            ]);

            Log::info('Reset stuck integration status', [
                'integration_id' => $integration->integration_id,
                'new_status' => $newStatus,
                'historical_import_status' => $historicalStatus,
            ]);

            $this->info("Reset integration #{$integration->integration_id} status to '{$newStatus}'");
        }

        $this->info('Done fixing stuck imports.');
        return 0;
    }
}
