<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Company;
use App\Models\GeneratedReport;
use App\Models\SyncLog;
use App\Models\DataRetentionLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

final class CleanupExpiredDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600; 

    public function handle(): void
    {
        Log::info('Starting nightly data cleanup');

        $companies = Company::where('status', 'active')->get();

        foreach ($companies as $company) {
            try {
                $this->cleanupCompanyData($company);
            } catch (\Exception $e) {
                Log::error('Cleanup failed for company', [
                    'company_id' => $company->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Nightly data cleanup completed');
    }

    private function cleanupCompanyData(Company $company): void
    {
        $retentionSettings = $company->settings['data_retention'] ?? [];

        $this->cleanupGeneratedReports($company, $retentionSettings);

        $this->cleanupSyncLogs($company, $retentionSettings);

        $this->cleanupIntegrationData($company, $retentionSettings);

        $this->hardDeleteExpiredRecords($company);

        $this->cleanupOrphanedFiles($company);
    }

    /**
     * Clean up old IntegrationData records (TimescaleDB hypertable)
     * 
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupIntegrationData(Company $company, array $retentionSettings): void
    {
        $rawEventsDays = $retentionSettings['historical_data']['raw_events_days'] ?? 90;
        $cutoffDate = now()->subDays($rawEventsDays);

        // Note: IntegrationData is a TimescaleDB hypertable
        // TimescaleDB handles compression and retention automatically via policies
        // This is a manual cleanup for additional control
        $deletedCount = \Illuminate\Support\Facades\DB::table('integration_data')
            ->where('company_id', $company->company_id)
            ->where('data_timestamp', '<', $cutoffDate)
            ->delete();

        if ($deletedCount > 0) {
            DataRetentionLog::create([
                'company_id' => $company->company_id,
                'record_type' => 'IntegrationData',
                'records_purged' => $deletedCount,
                'retention_policy' => [
                    'raw_events_days' => $rawEventsDays,
                    'cutoff_date' => $cutoffDate->toDateString(),
                ],
                'purged_at' => now(),
            ]);

            Log::info('IntegrationData cleaned up', [
                'company_id' => $company->company_id,
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * Hard delete soft-deleted records beyond grace period (30 days)
     */
    private function hardDeleteExpiredRecords(Company $company): void
    {
        $gracePeriodDays = 30;
        $cutoffDate = now()->subDays($gracePeriodDays);

        $expiredReports = GeneratedReport::onlyTrashed()
            ->where('company_id', $company->company_id)
            ->where('deleted_at', '<', $cutoffDate)
            ->get();

        $deletedCount = 0;

        foreach ($expiredReports as $report) {
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }
            $report->forceDelete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            Log::info('Hard deleted expired records', [
                'company_id' => $company->company_id,
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * Clean up orphaned files without database records
     */
    private function cleanupOrphanedFiles(Company $company): void
    {
        $reportFiles = Storage::files("reports/company_{$company->company_id}");
        $deletedCount = 0;

        foreach ($reportFiles as $filePath) {
            $exists = GeneratedReport::where('company_id', $company->company_id)
                ->where('file_path', $filePath)
                ->exists();

            if (!$exists) {
                Storage::delete($filePath);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            Log::info('Orphaned files cleaned up', [
                'company_id' => $company->company_id,
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupGeneratedReports(Company $company, array $retentionSettings): void
    {
        $retentionMonths = $retentionSettings['generated_reports_months'] ?? 36;
        $cutoffDate = now()->subMonths($retentionMonths);

        $expiredReports = GeneratedReport::where('company_id', $company->company_id)
            ->where('generated_at', '<', $cutoffDate)
            ->get();

        $deletedCount = 0;

        foreach ($expiredReports as $report) {
            
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }
 
            $report->delete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            
            DataRetentionLog::create([
                'company_id' => $company->company_id,
                'record_type' => 'GeneratedReport',
                'records_purged' => $deletedCount,
                'retention_policy' => [
                    'retention_months' => $retentionMonths,
                    'cutoff_date' => $cutoffDate->toDateString(),
                ],
                'purged_at' => now(),
            ]);

            Log::info('Generated reports cleaned up', [
                'company_id' => $company->company_id,
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $retentionSettings
     */
    private function cleanupSyncLogs(Company $company, array $retentionSettings): void
    {
        $retentionMonths = $retentionSettings['integration_sync_logs_months'] ?? 12;
        $cutoffDate = now()->subMonths($retentionMonths);

        $deletedCount = SyncLog::whereHas('integration', function ($query) use ($company) {
            $query->where('company_id', $company->company_id);
        })
        ->where('started_at', '<', $cutoffDate)
        ->delete();

        if ($deletedCount > 0) {
            DataRetentionLog::create([
                'company_id' => $company->company_id,
                'record_type' => 'SyncLog',
                'records_purged' => $deletedCount,
                'retention_policy' => [
                    'retention_months' => $retentionMonths,
                    'cutoff_date' => $cutoffDate->toDateString(),
                ],
                'purged_at' => now(),
            ]);

            Log::info('Sync logs cleaned up', [
                'company_id' => $company->company_id,
                'deleted_count' => $deletedCount,
            ]);
        }
    }
}
