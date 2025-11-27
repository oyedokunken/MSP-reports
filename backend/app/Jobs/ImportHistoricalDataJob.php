<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\HistoricalImportJob as HistoricalImportJobModel;
use App\Models\Integration;
use App\Services\RateLimiter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Integrations\Autotask\AutotaskIntegration;
use App\Integrations\Datto\DattoIntegration;
use App\Integrations\Sendmarc\SendmarcIntegration;
use App\Integrations\ITGlue\ITGlueIntegration;
use App\Integrations\SaaS\SaaSIntegration;


class ImportHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public $backoff = [60, 300, 900]; // Exponential backoff: 1 min, 5 min, 15 min

    protected HistoricalImportJobModel $importJob;
    protected Carbon $chunkDate;

    public function __construct(HistoricalImportJobModel $importJob, Carbon $chunkDate)
    {
        $this->importJob = $importJob;
        $this->chunkDate = $chunkDate;
    }

    public function handle(RateLimiter $rateLimiter): void
    {
        try {
            $integration = $this->importJob->integration;

            
            if ($this->importJob->status === 'queued') {
                $this->importJob->update([
                    'status' => 'running',
                    'started_at' => now(),
                ]);
            }
            
            $integrationClass = $this->loadIntegrationClass($integration);

            if (!$integrationClass) {
                throw new \Exception("Integration type {$integration->integration_type} not found.");
            }

            
            if (!$rateLimiter->waitForSlot($integration, 120)) {
                throw new \Exception("Rate limit exceeded for historical import.");
            }

            $result = $integrationClass->getHistoricalData(
                $this->chunkDate,
                $this->chunkDate->copy()->endOfDay()
            );

            
            $rateLimiter->recordRequest($integration);

            
            $recordsImported = count($result['data'] ?? []);
            $this->importJob->records_imported += $recordsImported;
            $this->importJob->current_position = $this->chunkDate;

            
            $totalDays = $this->importJob->import_start_date->diffInDays($this->importJob->import_end_date);
            $completedDays = $this->importJob->import_start_date->diffInDays($this->chunkDate);
            $this->importJob->progress_percent = min(100, round(($completedDays / $totalDays) * 100));

            $this->importJob->save();

            
            $nextDate = $this->chunkDate->copy()->addDay();
            if ($nextDate->lte($this->importJob->import_end_date)) {
                ImportHistoricalDataJob::dispatch($this->importJob, $nextDate);
            } else {
                
                $this->importJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'progress_percent' => 100,
                ]);

                Log::info('Historical import completed', [
                    'import_job_id' => $this->importJob->historical_import_job_id,
                    'records_imported' => $this->importJob->records_imported,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Historical import chunk failed', [
                'import_job_id' => $this->importJob->historical_import_job_id,
                'chunk_date' => $this->chunkDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            $this->importJob->increment('error_count');
            $this->importJob->save();

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Historical import job failed permanently after all retries', [
            'import_job_id' => $this->importJob->historical_import_job_id,
            'integration_id' => $this->importJob->integration_id,
            'company_id' => $this->importJob->company_id,
            'chunk_date' => $this->chunkDate->toDateString(),
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Mark import job as failed
        $this->importJob->update([
            'status' => 'failed',
        ]);

        // Mark integration as error if historical import fails
        $integration = $this->importJob->integration;
        $integration->update([
            'status' => 'error',
            'historical_import_status' => 'failed',
            'error_message' => "Historical import failed: {$exception->getMessage()}",
        ]);

        // TODO: Send notification email to company admins
        // Will be implemented in future update
        // $company = $integration->company;
        // $notificationEmails = $company->settings['integrations']['sync_failure_notifications'] ?? [];
    }

    private function loadIntegrationClass(Integration $integration)
    {
        $integrationClasses = [
            'Autotask' => AutotaskIntegration::class,
            'Datto' => DattoIntegration::class,
            'Sendmarc' => SendmarcIntegration::class,
            'ITGlue' => ITGlueIntegration::class,
            'SaaS' => SaaSIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            return new $className($integration);
        }

        return null;
    }
}
