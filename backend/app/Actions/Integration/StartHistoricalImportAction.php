<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Jobs\ImportHistoricalDataJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Integration;
use App\Models\SyncLog;
use App\Services\LicenseChecker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class StartHistoricalImportAction
{
    public function __construct(
        private LicenseChecker $licenseChecker
    ) {}

    /**
     * @param array{start_date: string, end_date: string} $data
     * @return array{job_id: int, message: string}
     */
    public function execute(Company $company, int $integrationId, array $data): array
    {
        $integration = $company->integrations()
            ->where('integration_id', $integrationId)
            ->first();

        if (!$integration) {
            throw ValidationException::withMessages([
                'integration_id' => ['Integration not found for this company.'],
            ]);
        }

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $requestedMonths = $startDate->diffInMonths($endDate);

        $retentionCheck = $this->licenseChecker->canExtendRetention($company, (int) $requestedMonths);

        if (!$retentionCheck['allowed']) {
            throw ValidationException::withMessages([
                'license' => [$retentionCheck['reason']],
            ]);
        }

        $runningJob = $integration->historicalImportJobs()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($runningJob) {
            throw ValidationException::withMessages([
                'job' => ['Historical import is already running for this integration.'],
            ]);
        }

        $job = $integration->historicalImportJobs()->create([
            'company_id' => $company->company_id,
            'import_start_date' => $startDate,
            'import_end_date' => $endDate,
            'current_position' => $startDate,
            'status' => 'queued',
        ]);

        $chunks = [];
        $tempDate = $startDate->copy();
        while ($tempDate->lt($endDate)) {
            $chunks[] = $tempDate->copy();
            $tempDate->addDays(7);
        }

        $totalJobs = count($chunks);
        $job->update(['total_jobs' => $totalJobs, 'jobs_completed' => 0]);

        foreach ($chunks as $chunkDate) {
            ImportHistoricalDataJob::dispatch($job, $chunkDate);
        }

        $integration->update([
            'status' => 'importing_historical',
            'historical_import_status' => 'pending',
            'historical_import_progress' => 0
        ]);

        // Log to SyncLog
        SyncLog::create([
            'integration_id' => $integration->integration_id,
            'sync_type' => 'historical',
            'started_at' => now(),
            'status' => 'running',
            'records_synced' => 0,
            'records_failed' => 0,
            'rate_limit_hits' => 0,
            'error_messages' => null,
        ]);

        // Log to AuditLog
        $user = auth()->user();
        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'historical_import_queued',
            'actor_type' => $user ? 'User' : 'System',
            'actor_id' => $user?->user_id,
            'target_type' => Integration::class,
            'target_id' => $integration->integration_id,
            'event_data' => [
                'integration_name' => $integration->display_name,
                'import_start_date' => $startDate->toDateString(),
                'import_end_date' => $endDate->toDateString(),
                'total_chunks' => $totalJobs,
                'job_id' => $job->historical_import_job_id,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        Log::info('Historical import queued and logged', [
            'integration_id' => $integration->integration_id,
            'job_id' => $job->historical_import_job_id,
            'total_chunks' => $totalJobs,
        ]);

        return [
            'job_id' => $job->historical_import_job_id,
            'message' => "Historical import started with {$totalJobs} parallel chunks.",
        ];
    }
}
