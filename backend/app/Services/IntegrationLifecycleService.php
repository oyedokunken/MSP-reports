<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Integration;
use App\Models\Company;
use App\Services\SyncEngine;
use App\Services\LicenseChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\ImportHistoricalDataJob;

/**
 * Service dedicated to managing the lifecycle of an Integration:
 * Onboarding, Offboarding, and State Transitions.
 */
class IntegrationLifecycleService
{
    protected SyncEngine $syncEngine;
    protected LicenseChecker $licenseChecker;
    protected IntegrationService $baseIntegrationService;

    public function __construct(
        SyncEngine $syncEngine,
        LicenseChecker $licenseChecker,
        IntegrationService $baseIntegrationService
    ) {
        $this->syncEngine = $syncEngine;
        $this->licenseChecker = $licenseChecker;
        $this->baseIntegrationService = $baseIntegrationService;
    }

    /**
     * The "Zero-Touch" Onboarding Flow.
     * Creates the integration and immediately kicks off necessary automation.
     * 
     * @param Company $company
     * @param array<string, mixed> $data
     * @return Integration
     */
    public function onboardNewIntegration(Company $company, array $data): Integration
    {
        Log::info("Starting onboarding for new integration", [
            'company_id' => $company->company_id,
            'type' => $data['integration_type']
        ]);

        // 1. Create the Integration Record (Standard CRUD)
        // We reuse the base service for the heavy lifting of DB creation/Auth testing
        $integration = $this->baseIntegrationService->createIntegration($company, $data);

        // 2. The "Kick-off" Phase (Automation)
        if ($integration->status->value === 'active') {
            $this->kickstartAutomation($integration);
        }

        return $integration;
    }

    /**
     * Triggers the initial waves of synchronization for a fresh integration.
     */
    public function kickstartAutomation(Integration $integration): void
    {
        Log::info("Kickstarting automation for integration", ['integration_id' => $integration->integration_id]);

        try {
            // A. Immediate "Current State" Sync
            // This populates the UI tables and mapping wizards ASAP.
            // We use 'critical' priority to ensure the user gets feedback instantly.
            $this->syncEngine->triggerSync($integration, 'current_state', 'critical');
            Log::info("Triggered initial critical sync", ['integration_id' => $integration->integration_id]);

            // B. Automatic Historical Backfill (The "Zero-Config" delighter)
            // We automatically queue a 30-day import so graphs aren't empty.
            // We verify license first to be safe, though usually included.
            $this->triggerDefaultHistoricalBackfill($integration);

        } catch (\Exception $e) {
            // We log but don't fail the request - the integration was created successfully.
            // The Scheduler will pick it up later if this initial kick-off failed.
            Log::error("Failed to complete automation kickstart", [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queues a default 30-day historical import if allowed.
     */
    protected function triggerDefaultHistoricalBackfill(Integration $integration): void
    {
        // Check if type supports historical
        $config = $this->baseIntegrationService->getIntegrationTypeConfig($integration->integration_type);
        if (empty($config['supports_historical'])) {
            return;
        }

        // Default to last 30 days
        $endDate = now()->endOfDay();
        $startDate = now()->subDays(30)->startOfDay();
        
        // Double check retention license just in case
        $months = 1; // 30 days
        $retentionCheck = $this->licenseChecker->canExtendRetention($integration->company, $months);
        
        if (!$retentionCheck['allowed']) {
            Log::warning("Skipping automatic historical backfill due to license limits", [
                'integration_id' => $integration->integration_id
            ]);
            return;
        }

        // Create the Job Record
        /** @var \App\Models\HistoricalImportJob $job */
        $job = $integration->historicalImportJobs()->create([
            'company_id' => $integration->company_id,
            'import_start_date' => $startDate,
            'import_end_date' => $endDate,
            'current_position' => $startDate,
            'status' => 'queued',
            'triggered_by' => 'system_onboarding' // Useful for analytics
        ]);

        // Calculate 7-day chunks for parallel processing
        $chunks = [];
        $tempDate = $startDate->copy();
        while ($tempDate->lt($endDate)) {
            $chunks[] = $tempDate->copy();
            $tempDate->addDays(7);
        }

        $totalJobs = count($chunks);
        $job->update([
            'total_jobs' => $totalJobs,
            'jobs_completed' => 0
        ]);

        // Dispatch all chunks in parallel
        foreach ($chunks as $chunkDate) {
            ImportHistoricalDataJob::dispatch($job, $chunkDate);
        }

        $integration->update([
            'status' => 'importing_historical', // It's active AND importing
            'historical_import_status' => 'pending'
        ]);

        Log::info("Queued automatic 30-day historical backfill with {$totalJobs} parallel chunks", ['job_id' => $job->historical_import_job_id]);
    }
}
