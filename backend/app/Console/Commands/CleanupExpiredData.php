<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\GeneratedReport;
use App\Models\SyncLog;
use App\Models\IntegrationData;
use App\Models\DataRetentionLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredData extends Command
{
    protected $signature = 'data:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up expired data based on company retention policies';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        $this->info('Starting data cleanup process...');
        $this->newLine();

        $companies = Company::all();
        $totalDeleted = 0;

        foreach ($companies as $company) {
            $this->info("Processing company: {$company->name} (ID: {$company->company_id})");
            
            $retentionSettings = $company->settings['data_retention'] ?? [];
            $deleted = 0;

            // Phase 1: Soft delete expired records
            $deleted += $this->cleanupGeneratedReports($company, $retentionSettings, $dryRun);
            $deleted += $this->cleanupSyncLogs($company, $retentionSettings, $dryRun);
            $deleted += $this->cleanupIntegrationData($company, $retentionSettings, $dryRun);

            // Phase 2: Hard delete soft-deleted records older than grace period (30 days)
            $deleted += $this->hardDeleteExpiredRecords($company, $dryRun);

            // Phase 3: Clean up orphaned files
            $deleted += $this->cleanupOrphanedFiles($company, $dryRun);

            $totalDeleted += $deleted;
            
            // Log retention cleanup
            if (!$dryRun && $deleted > 0) {
                DataRetentionLog::create([
                    'company_id' => $company->company_id,
                    'record_type' => 'mixed',
                    'records_purged' => $deleted,
                    'retention_policy' => $retentionSettings,
                    'purged_at' => now(),
                ]);
            }

            $this->info("  Cleaned up {$deleted} records for {$company->name}");
            $this->newLine();
        }

        $this->info("Cleanup complete! Total records processed: {$totalDeleted}");
        
        if ($dryRun) {
            $this->warn('This was a DRY RUN - no data was actually deleted');
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupGeneratedReports(Company $company, array $retentionSettings, bool $dryRun): int
    {
        $retentionDays = $retentionSettings['generated_reports_months'] ?? 36;
        $retentionDays = $retentionDays * 30;
        
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $query = GeneratedReport::where('company_id', $company->company_id)
            ->where('created_at', '<', $cutoffDate)
            ->whereNull('deleted_at');

        $count = $query->count();

        if ($count > 0) {
            $this->line("  - Generated Reports: {$count} reports older than {$retentionDays} days");
            
            if (!$dryRun) {
                $query->delete();
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupSyncLogs(Company $company, array $retentionSettings, bool $dryRun): int
    {
        $retentionMonths = $retentionSettings['integration_sync_logs_months'] ?? 12;
        $cutoffDate = Carbon::now()->subMonths($retentionMonths);

        $query = SyncLog::whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->where('created_at', '<', $cutoffDate)
            ->whereNull('deleted_at');

        $count = $query->count();

        if ($count > 0) {
            $this->line("  - Sync Logs: {$count} logs older than {$retentionMonths} months");
            
            if (!$dryRun) {
                $query->delete();
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupIntegrationData(Company $company, array $retentionSettings, bool $dryRun): int
    {
        $rawEventsDays = $retentionSettings['historical_data']['raw_events_days'] ?? 90;
        $cutoffDate = Carbon::now()->subDays($rawEventsDays);

        // Note: IntegrationData is a TimescaleDB hypertable
        // TimescaleDB handles compression and retention automatically via policies
        // This is a manual cleanup for additional control
        
        $query = DB::table('integration_data')
            ->where('company_id', $company->company_id)
            ->where('data_timestamp', '<', $cutoffDate);

        $count = $query->count();

        if ($count > 0) {
            $this->line("  - Integration Data: {$count} records older than {$rawEventsDays} days");
            
            if (!$dryRun) {
                $query->delete();
            }
        }

        return $count;
    }

    private function hardDeleteExpiredRecords(Company $company, bool $dryRun): int
    {
        $gracePeriodDays = 30;
        $cutoffDate = Carbon::now()->subDays($gracePeriodDays);
        $count = 0;

        $reportQuery = GeneratedReport::where('company_id', $company->company_id)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoffDate);

        $reportCount = $reportQuery->count();
        
        if ($reportCount > 0) {
            $this->line("  - Hard deleting {$reportCount} soft-deleted reports (grace period expired)");
            
            if (!$dryRun) {
                // Delete associated files first
                foreach ($reportQuery->get() as $report) {
                    if ($report->file_path && Storage::exists($report->file_path)) {
                        Storage::delete($report->file_path);
                    }
                }
                $reportQuery->forceDelete();
            }
            
            $count += $reportCount;
        }

        return $count;
    }

    private function cleanupOrphanedFiles(Company $company, bool $dryRun): int
    {
        $count = 0;
        
        $reportFiles = Storage::files("reports/company_{$company->company_id}");
        
        foreach ($reportFiles as $filePath) {
            $exists = GeneratedReport::where('company_id', $company->company_id)
                ->where('file_path', $filePath)
                ->exists();
            
            if (!$exists) {
                $this->line("  - Orphaned file: {$filePath}");
                
                if (!$dryRun) {
                    Storage::delete($filePath);
                }
                
                $count++;
            }
        }

        if ($count > 0) {
            $this->line("  - Cleaned up {$count} orphaned files");
        }

        return $count;
    }
}
