<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Integration\CreateIntegrationAction;
use App\Actions\Integration\DeleteIntegrationAction;
use App\Actions\Integration\SetSourceOfTruthAction;
use App\Actions\Integration\TriggerSyncAction;
use App\Actions\Integration\UpdateIntegrationAction;
use App\Actions\Integration\StartHistoricalImportAction;
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
use App\Models\Integration;

final class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationService $integrationService,
        protected \App\Services\IntegrationLifecycleService $lifecycleService
    ) {
    }

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
            ->with([
                'syncLogs' => function ($query) {
                    $query->latest('started_at')->limit(1);
                }
            ])
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
            $integration = $action->execute($request->user()->company, $validated, $request->user());
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
            $syncLog = $action->execute($company, $id, $request->user());
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
            $action->execute($request->user()->company, $id, $request->user());
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
                'success' => false,
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        // Actually test the connection by calling the integration's healthCheck method
        try {
            $integrationClass = $this->getIntegrationClass($integration);

            if (!$integrationClass) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => "Integration type {$integration->integration_type} not supported."
                ], 400);
            }

            $connectionHealthy = $integrationClass->healthCheck();

            $lastSyncAge = $integration->last_sync_at
                ? now()->diffInMinutes($integration->last_sync_at)
                : null;

            $checks = [
                [
                    'name' => 'Connection',
                    'status' => $connectionHealthy ? 'passed' : 'failed',
                    'message' => $connectionHealthy ? 'Successfully connected to API' : 'Failed to connect to API'
                ],
                [
                    'name' => 'Credentials',
                    'status' => $integration->credentials !== null ? 'passed' : 'failed',
                    'message' => $integration->credentials !== null ? 'Credentials are configured' : 'No credentials configured'
                ],
                [
                    'name' => 'Recent Sync',
                    'status' => ($lastSyncAge !== null && $lastSyncAge < 1440) ? 'passed' : 'warning',
                    'message' => $lastSyncAge !== null
                        ? "Last sync was {$lastSyncAge} minutes ago"
                        : 'Never synced'
                ],
                [
                    'name' => 'Error Status',
                    'status' => $integration->error_message === null ? 'passed' : 'warning',
                    'message' => $integration->error_message ?? 'No errors'
                ],
            ];

            return response()->json([
                'success' => $connectionHealthy,
                'status' => $connectionHealthy ? 'healthy' : 'unhealthy',
                'message' => $connectionHealthy ? 'Integration is healthy' : 'Connection test failed',
                'checks' => $checks,
                'data' => [
                    'healthy' => $connectionHealthy,
                    'status' => $integration->status,
                    'last_sync_at' => $integration->last_sync_at,
                    'last_sync_status' => $integration->last_sync_status,
                    'last_sync_age_minutes' => $lastSyncAge,
                    'error_message' => $integration->error_message,
                    'summary_checks' => [
                        'credentials_valid' => $integration->credentials !== null && $connectionHealthy,
                        'recent_sync' => ($lastSyncAge !== null && $lastSyncAge < 1440),
                        'no_errors' => $integration->error_message === null,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Health check failed', [
                'integration_id' => $integration->integration_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'checks' => [
                    [
                        'name' => 'Connection',
                        'status' => 'failed',
                        'message' => $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    /**
     * Get the integration class instance for a given integration
     */
    private function getIntegrationClass(Integration $integration): ?\App\Integrations\Contracts\IntegrationInterface
    {
        $integrationClasses = [
            'Autotask' => \App\Integrations\Autotask\AutotaskIntegration::class,
            'Datto' => \App\Integrations\Datto\DattoIntegration::class,
            'Sendmarc' => \App\Integrations\Sendmarc\SendmarcIntegration::class,
            'ITGlue' => \App\Integrations\ITGlue\ITGlueIntegration::class,
            'LibreNMS' => \App\Integrations\LibreNMS\LibreNMSIntegration::class,
            'SaaS' => \App\Integrations\SaaS\SaaSIntegration::class,
            'BitDefender' => \App\Integrations\BitDefender\BitDefenderIntegration::class,
            'VeeamOne' => \App\Integrations\VeeamOne\VeeamOneIntegration::class,
            'CIPP' => \App\Integrations\CIPP\CIPPIntegration::class,
            'Barracuda' => \App\Integrations\Barracuda\BarracudaIntegration::class,
            'SharePoint' => \App\Integrations\SharePoint\SharePointIntegration::class,
            'ActiveDirectory' => \App\Integrations\ActiveDirectory\ActiveDirectoryIntegration::class,
            'ThreatLocker' => \App\Integrations\ThreatLocker\ThreatLockerIntegration::class,
            'Vonahi' => \App\Integrations\Vonahi\VonahiIntegration::class,
        ];

        $className = $integrationClasses[$integration->integration_type] ?? null;

        if ($className && class_exists($className)) {
            $instance = new $className($integration);
            if ($instance instanceof \App\Integrations\Contracts\IntegrationInterface) {
                return $instance;
            }
        }

        return null;
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

    public function startHistoricalImport(StartHistoricalImportRequest $request, int $id, StartHistoricalImportAction $action): JsonResponse
    {
        try {
            /** @var array{start_date: string, end_date: string} $validated */
            $validated = $request->validated();
            $result = $action->execute($request->user()->company, $id, $validated);

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'job_id' => $result['job_id']
            ]);
        } catch (ValidationException $e) {
            $status = isset($e->errors()['license']) ? 403 : (isset($e->errors()['integration_id']) ? 404 : 400);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                // preserve specific structure if needed by frontend, but usually message is enough
            ], $status);
        }
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
