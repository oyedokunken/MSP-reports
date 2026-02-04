<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\HistoricalImportJob;
use Illuminate\Console\Command;

class FixStuckImports extends Command
{
    protected $signature = 'integrations:fix-stuck-imports 
                            {--force : Force reset all importing integrations to active}
                            {--list : Just list stuck integrations without fixing}';

    protected $description = 'Fix integrations stuck in importing_historical status';

    public function handle(): int
    {
        $this->info('Checking for stuck imports...');

        // Find integrations stuck in importing_historical status
        $stuckIntegrations = Integration::where('status', 'importing_historical')->get();

        if ($stuckIntegrations->isEmpty()) {
            $this->info('No integrations stuck in importing_historical status.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Display Name', 'Company', 'Status', 'Import Progress', 'Created At'],
            $stuckIntegrations->map(fn($i) => [
                $i->integration_id,
                $i->integration_type,
                $i->display_name,
                $i->company->name ?? 'N/A',
                $i->status->value,
                ($i->historical_import_progress ?? 0) . '%',
                $i->created_at->diffForHumans(),
            ])
        );

        if ($this->option('list')) {
            return Command::SUCCESS;
        }

        foreach ($stuckIntegrations as $integration) {
            $this->line('');
            $this->info("Processing: {$integration->display_name} ({$integration->integration_type})");

            // Check for associated historical import jobs
            $importJobs = HistoricalImportJob::where('integration_id', $integration->integration_id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($importJobs->isEmpty()) {
                $this->warn("  No historical import jobs found. Setting to active.");
                $integration->update([
                    'status' => 'active',
                    'historical_import_status' => null,
                ]);
                continue;
            }

            $latestJob = $importJobs->first();
            $this->line("  Latest import job: ID {$latestJob->historical_import_job_id}");
            $this->line("    Status: {$latestJob->status}");
            $this->line("    Progress: {$latestJob->progress_percent}%");
            $this->line("    Records imported: {$latestJob->records_imported}");
            $this->line("    Jobs completed: {$latestJob->jobs_completed}/{$latestJob->total_jobs}");
            $this->line("    Created: {$latestJob->created_at->diffForHumans()}");

            // Check if the job is actually stuck (created more than 2 hours ago and not completed)
            $isStuck = $latestJob->created_at->lt(now()->subHours(2)) 
                && !in_array($latestJob->status, ['completed', 'failed']);

            if ($isStuck || $this->option('force')) {
                $reason = $this->option('force') ? 'Force flag used' : 'Job appears stuck (>2 hours old)';
                $this->warn("  {$reason}. Resetting integration to active.");

                // Mark import job as completed if it has imported data, otherwise failed
                $hasData = $latestJob->records_imported > 0;
                $latestJob->update([
                    'status' => $hasData ? 'completed' : 'failed',
                    'completed_at' => now(),
                ]);

                // Reset integration to active
                $integration->update([
                    'status' => 'active',
                    'historical_import_status' => $hasData ? 'completed' : 'failed',
                ]);

                $this->info("  Integration reset to active. Import marked as " . ($hasData ? 'completed' : 'failed'));
            } else {
                $this->line("  Job is recent or still processing. Use --force to reset anyway.");
            }
        }

        $this->line('');
        $this->info('Done!');

        return Command::SUCCESS;
    }
}
