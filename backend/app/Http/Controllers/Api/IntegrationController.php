<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Integration\CreateIntegrationAction;
use App\Actions\Integration\DeleteIntegrationAction;
use App\Actions\Integration\SetSourceOfTruthAction;
use App\Actions\Integration\TriggerSyncAction;
use App\Actions\Integration\UpdateIntegrationAction;
use App\Http\Requests\Integration\CreateIntegrationRequest;
use App\Http\Requests\Integration\CreateSharePointFolderRequest;
use App\Http\Requests\Integration\SetSourceOfTruthRequest;
use App\Http\Requests\Integration\StartHistoricalImportRequest;
use App\Http\Requests\Integration\UpdateIntegrationRequest;
use App\Http\Resources\IntegrationResource;
use App\Http\Controllers\Controller;
use App\Services\IntegrationService;
use App\Services\LicenseChecker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService,
        protected \App\Services\IntegrationLifecycleService $lifecycleService
    ) {}

    public function getIntegrations(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = $this->integrationService->scanAvailableIntegrations();

        $configuredIntegrations = $company->integrations()
            ->withCount([
                'integrationCustomers as unmapped_customers_count' => function ($query) {
                    $query->whereNull('customer_id');
                },
                'integrationCustomers as total_customers_count'
            ])
            ->with(['syncLogs' => function ($query) {
                $query->latest('started_at')->limit(1);
            }])
            ->get();

        return response()->json([
            'available_types' => $availableTypes,
            'configured' => IntegrationResource::collection($configuredIntegrations),
        ]);
    }

    public function getIntegrationTypeConfig(string $integrationType): JsonResponse
    {
        try {
            $config = $this->integrationService->getIntegrationTypeConfig($integrationType);
            return response()->json([
                'success' => true,
                'config' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function createIntegration(CreateIntegrationRequest $request, CreateIntegrationAction $action): JsonResponse
    {
        try {
            /** @var array{integration_type: string, display_name: string, status?: string, credentials?: array<string, mixed>|null, configuration?: array<string, mixed>|null, is_source_of_truth?: bool|null} $validated */
            $validated = $request->validated();
            $integration = $action->execute($request->user()->company, $validated);
            return response()->json([
                'status' => 'success',
                'data' => new IntegrationResource($integration)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function triggerSync(Request $request, int $id, TriggerSyncAction $action): JsonResponse
    {
        $company = $request->user()->company;

        try {
            $syncLog = $action->execute($company, $id);
            $integration = $company->integrations()->find($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Sync queued successfully.',
                'data' => [
                    'sync_log_id' => $syncLog->sync_id,
                    'status' => $syncLog->status,
                    'integration_id' => $integration->integration_id,
                    'integration_type' => $integration->integration_type,
                ]
            ], 202);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function getIntegrationCustomers(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $customers = $integration->integrationCustomers()
            ->select([
                'integration_customer_id',
                'external_id',
                'name as customer_name',
                'active as active_status',
                'last_seen_at',
                'metadata as customer_metadata',
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'integration' => [
                'integration_id' => $integration->integration_id,
                'display_name' => $integration->display_name,
                'integration_type' => $integration->integration_type,
            ],
            'customers' => $customers
        ]);
    }

    public function setSourceIntegration(SetSourceOfTruthRequest $request, SetSourceOfTruthAction $action): JsonResponse
    {
        try {
            $result = $action->execute($request->user()->company, $request->validated()['integration_id']);
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'integration_id' => $result['integration']->integration_id,
                    'integration_type' => $result['integration']->integration_type,
                    'display_name' => $result['integration']->display_name,
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function updateIntegration(UpdateIntegrationRequest $request, int $id, UpdateIntegrationAction $action): JsonResponse
    {
        try {
            $integration = $action->execute($request->user()->company, $id, $request->validated());
            return response()->json([
                'status' => 'success',
                'data' => new IntegrationResource($integration)
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function deleteIntegration(Request $request, int $id, DeleteIntegrationAction $action): JsonResponse
    {
        try {
            $action->execute($request->user()->company, $id);
            return response()->json([
                'status' => 'success',
                'message' => 'Integration deleted successfully.'
            ]);
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'source of truth') ? 400 : 404;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function getSyncLogs(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $limit = min($request->input('limit', 50), 100);

        $syncLogs = $integration->syncLogs()
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();

        $latestSync = $syncLogs->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'integration' => [
                    'integration_id' => $integration->integration_id,
                    'display_name' => $integration->display_name,
                    'integration_type' => $integration->integration_type,
                ],
                'latest_sync' => $latestSync ? [
                    'sync_id' => $latestSync->sync_id,
                    'started_at' => $latestSync->started_at,
                    'completed_at' => $latestSync->completed_at,
                    'status' => $latestSync->status,
                    'records_synced' => $latestSync->records_synced,
                    'customers_mapped' => $latestSync->customers_mapped ?? [],
                ] : null,
                'sync_logs' => $syncLogs,
            ]
        ]);
    }

    public function healthCheck(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $isHealthy = $integration->status->value === 'active';
        $lastSyncAge = $integration->last_sync_at
            ? now()->diffInMinutes($integration->last_sync_at)
            : null;

        $health = [
            'healthy' => $isHealthy,
            'status' => $integration->status,
            'last_sync_at' => $integration->last_sync_at,
            'last_sync_status' => $integration->last_sync_status,
            'last_sync_age_minutes' => $lastSyncAge,
            'error_message' => $integration->error_message,
            'checks' => [
                'credentials_valid' => $integration->credentials !== null,
                'recent_sync' => $lastSyncAge !== null && $lastSyncAge < 1440,
                'no_errors' => $integration->error_message === null,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $health
        ]);
    }

    public function getRateLimitStatus(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $recentLogs = $integration->syncLogs()
            ->where('started_at', '>', now()->subHour())
            ->get();

        $totalHits = $recentLogs->sum('rate_limit_hits');
        $requestsLastHour = $recentLogs->count();

        $integrationConfig = $this->getIntegrationConfig($integration->integration_type);
        $rateLimits = $integrationConfig['rate_limits'] ?? [];

        $limit = [
            'per_minute' => $rateLimits['requests_per_minute'] ?? 60,
            'per_hour' => $rateLimits['requests_per_hour'] ?? 1000,
            'burst_allowance' => $rateLimits['burst_allowance'] ?? 10,
        ];

        $rateLimitStatus = [
            'integration_type' => $integration->integration_type,
            'limits' => $limit,
            'current_usage' => [
                'requests_last_hour' => $requestsLastHour,
                'rate_limit_hits' => $totalHits,
            ],
            'percentage_used' => round(($requestsLastHour / $limit['per_hour']) * 100, 2),
            'status' => $totalHits > 0 ? 'throttled' : 'normal',
        ];

        return response()->json([
            'status' => 'success',
            'data' => $rateLimitStatus
        ]);
    }

    public function startHistoricalImport(StartHistoricalImportRequest $request, int $id, LicenseChecker $licenseChecker): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $validated = $request->validated();

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();
        $requestedMonths = $startDate->diffInMonths($endDate);

        $retentionCheck = $licenseChecker->canExtendRetention($company, (int) $requestedMonths);

        if (!$retentionCheck['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => $retentionCheck['reason'],
                'requested_months' => $requestedMonths
            ], 403);
        }

        $runningJob = $integration->historicalImportJobs()
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($runningJob) {
            return response()->json([
                'status' => 'error',
                'message' => 'Historical import is already running for this integration.'
            ], 400);
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
            \App\Jobs\ImportHistoricalDataJob::dispatch($job, $chunkDate);
        }

        $integration->update([
            'status' => 'importing_historical',
            'historical_import_status' => 'pending',
            'historical_import_progress' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Historical import started with {$totalJobs} parallel chunks.",
            'job_id' => $job->historical_import_job_id
        ]);
    }

    public function browseSharePoint(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->where('integration_type', 'SharePoint')
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'SharePoint integration not found.'
            ], 404);
        }

        $path = $request->query('path', '');

        try {
            $sharepoint = new \App\Integrations\SharePoint\SharePointIntegration($integration);
            $items = $sharepoint->browse($path);

            return response()->json([
                'status' => 'success',
                'data' => $items,
                'path' => $path
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createSharePointFolder(CreateSharePointFolderRequest $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->where('integration_type', 'SharePoint')
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'SharePoint integration not found.'
            ], 404);
        }

        $validated = $request->validated();

        try {
            $sharepoint = new \App\Integrations\SharePoint\SharePointIntegration($integration);
            $folder = $sharepoint->createFolder($validated['path'] ?? '', $validated['name']);

            return response()->json([
                'status' => 'success',
                'data' => $folder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getHistoricalImportJobs(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $jobs = $integration->historicalImportJobs()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'integration' => $integration,
                'jobs' => $jobs,
            ]
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getIntegrationConfig(string $integrationType): array
    {
        $configPath = app_path("Integrations/{$integrationType}/config.php");
        if (file_exists($configPath)) {
            return include $configPath;
        }
        return [];
    }
}
