<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CleanupExpiredDataJob;
use App\Models\Company;
use App\Models\DataRetentionLog;
use App\Models\GeneratedReport;
use App\Models\Integration;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupExpiredDataJobTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'status' => 'active',
            'settings' => [
                'data_retention' => [
                    'generated_reports_months' => 1, // 1 month for tests
                    'integration_sync_logs_months' => 1,
                    'historical_data' => [
                        'raw_events_days' => 30,
                    ],
                ],
            ],
        ]);
    }

    public function test_cleanup_deletes_expired_generated_reports(): void
    {
        Storage::fake('local');

        // Create expired report (older than retention period)
        $expiredReport = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'generated_at' => now()->subMonths(2),
            'file_path' => 'reports/test_expired.pdf',
        ]);

        // Create recent report (should NOT be deleted)
        $recentReport = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'generated_at' => now()->subDays(10),
            'file_path' => 'reports/test_recent.pdf',
        ]);

        // Create the files
        Storage::put('reports/test_expired.pdf', 'expired content');
        Storage::put('reports/test_recent.pdf', 'recent content');

        // Run cleanup job
        $job = new CleanupExpiredDataJob();
        $job->handle();

        // Expired report should be deleted (soft deleted)
        $this->assertSoftDeleted('generated_reports', [
            'generated_report_id' => $expiredReport->generated_report_id,
        ]);

        // Recent report should still exist
        $this->assertDatabaseHas('generated_reports', [
            'generated_report_id' => $recentReport->generated_report_id,
        ]);

        // Retention log should be created
        $this->assertDatabaseHas('data_retention_logs', [
            'company_id' => $this->company->company_id,
            'record_type' => 'GeneratedReport',
        ]);
    }

    public function test_cleanup_deletes_expired_sync_logs(): void
    {
        // Create integration
        $integration = Integration::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        // Create expired sync log
        $expiredLog = SyncLog::factory()->create([
            'integration_id' => $integration->integration_id,
            'started_at' => now()->subMonths(2),
        ]);

        // Create recent sync log
        $recentLog = SyncLog::factory()->create([
            'integration_id' => $integration->integration_id,
            'started_at' => now()->subDays(10),
        ]);

        // Run cleanup job
        $job = new CleanupExpiredDataJob();
        $job->handle();

        // Expired log should be deleted (soft deleted)
        $this->assertSoftDeleted('sync_logs', [
            'sync_id' => $expiredLog->sync_id,
        ]);

        // Recent log should still exist
        $this->assertDatabaseHas('sync_logs', [
            'sync_id' => $recentLog->sync_id,
        ]);
    }

    public function test_cleanup_respects_company_retention_settings(): void
    {
        // Create company with longer retention
        $longRetentionCompany = Company::factory()->create([
            'status' => 'active',
            'settings' => [
                'data_retention' => [
                    'generated_reports_months' => 12, // 12 months
                ],
            ],
        ]);

        // Create 3-month old report
        $report = GeneratedReport::factory()->create([
            'company_id' => $longRetentionCompany->company_id,
            'generated_at' => now()->subMonths(3),
        ]);

        // Run cleanup job
        $job = new CleanupExpiredDataJob();
        $job->handle();

        // Report should NOT be deleted (3 months < 12 months retention)
        $this->assertDatabaseHas('generated_reports', [
            'generated_report_id' => $report->generated_report_id,
        ]);
    }

    public function test_cleanup_only_runs_for_active_companies(): void
    {
        // Deactivate company
        $this->company->update(['status' => 'suspended']);

        $expiredReport = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'generated_at' => now()->subMonths(2),
        ]);

        // Run cleanup job
        $job = new CleanupExpiredDataJob();
        $job->handle();

        // Report should NOT be deleted (company suspended)
        $this->assertDatabaseHas('generated_reports', [
            'generated_report_id' => $expiredReport->generated_report_id,
        ]);
    }

    public function test_hard_delete_removes_soft_deleted_records_after_grace_period(): void
    {
        Storage::fake('local');

        // Create and soft-delete report beyond grace period (30 days)
        $report = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'generated_at' => now()->subMonths(3),
            'file_path' => 'reports/old_deleted.pdf',
        ]);
        
        $report->deleted_at = now()->subDays(45);
        $report->save();

        Storage::put('reports/old_deleted.pdf', 'content');

        // Run cleanup job
        $job = new CleanupExpiredDataJob();
        $job->handle();

        // Report should be force deleted
        $this->assertDatabaseMissing('generated_reports', [
            'generated_report_id' => $report->generated_report_id,
        ]);
    }
}
