<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Integration;
use App\Models\AuditLog;
use App\Services\SyncEngine;
use App\Services\LicenseChecker;
use App\Services\IntegrationService;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    protected IntegrationService $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Get all available integrations and configured integrations
     */
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
            ->with(['syncLogs' => function($query) {
                $query->latest('started_at')->limit(1);
            }])
            ->get(['integration_id', 'integration_type', 'display_name', 'status', 'is_source_of_truth', 'last_sync_at', 'last_sync_status', 'sync_schedule'])
            ->map(function($integration) {
                $latestSync = $integration->syncLogs->first();
                return [
                    'integration_id' => $integration->integration_id,
                    'integration_type' => $integration->integration_type,
                    'display_name' => $integration->display_name,
                    'status' => $integration->status,
                    'is_source_of_truth' => $integration->is_source_of_truth,
                    'source_label' => $integration->is_source_of_truth ? '✅ Source of Truth' : null,
                    'last_sync_at' => $integration->last_sync_at,
                    'last_sync_status' => $integration->last_sync_status,
                    'unmapped_customers_count' => $integration->unmapped_customers_count ?? 0,
                    'total_customers_count' => $integration->total_customers_count ?? 0,
                    'next_sync_at' => $this->integrationService->calculateNextSyncAt($integration),
                    'records_synced' => $latestSync ? $latestSync->records_synced : 0,
                    'last_error' => $latestSync && $latestSync->status === 'failed' ? ($latestSync->error_messages[0] ?? null) : null,
                ];
            });

        return response()->json([
            'available_types' => $availableTypes,
            'configured' => $configuredIntegrations,
        ]);
    }


    /**
     * Get configuration and credential fields for a specific integration type
     */
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

    /**
     * Create a new integration
     */
    public function createIntegration(Request $request): JsonResponse
    {
        $availableTypes = $this->integrationService->scanAvailableIntegrations();
        
        $validated = $request->validate([
            'integration_type' => ['required', 'string', Rule::in($availableTypes)],
            'display_name' => 'required|string|min:2|max:255|regex:/^[A-Za-z0-9\s\-\_\.]+$/',
            'status' => ['required', 'string', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
            'credentials' => 'nullable|array',
            'configuration' => 'nullable|array',
            'is_source_of_truth' => 'nullable|boolean',
        ]);

        try {
            $integration = $this->integrationService->createIntegration($request->user()->company, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $integration
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function triggerSync(Request $request, $id)
    {
        $company = $request->user()->company;
        $integration = $company->integrations()->find($id);

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        try {
            $syncLog = $this->integrationService->triggerSync($integration);

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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 409);
        }
    }

    public function getIntegrationCustomers(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $request->merge(['integration_id' => intval($id)]);
        $validated = $request->validate([
            'integration_id' => 'required|integer|exists:integrations,integration_id',
        ]);

        $integration = $company->integrations()
            ->where('integration_id', $validated['integration_id'])
            ->first();

        if (! $integration) {
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

    public function setSourceIntegration(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validate([
            'integration_id' => 'required|integer|exists:integrations,integration_id',
        ]);

        $integration = $company->integrations()
            ->where('integration_id', $validated['integration_id'])
            ->first();

        if (! $integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $company->integrations()->update(['is_source_of_truth' => false]);
        $integration->update(['is_source_of_truth' => true]);

        $settings = $company->settings ?? [];
        if (!isset($settings['integrations'])) {
            $settings['integrations'] = [];
        }
        $settings['integrations']['source_of_truth_integration_id'] = $integration->integration_id;
        $company->settings = $settings;
        $company->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Source of truth integration updated successfully.',
            'data' => [
                'integration_id' => $integration->integration_id,
                'integration_type' => $integration->integration_type,
                'display_name' => $integration->display_name,
            ]
        ], 200);
    }

    public function updateIntegration(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|min:2|max:255|regex:/^[A-Za-z0-9\s\-\_\.]+$/',
            'status' => ['sometimes', 'string', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
            'credentials' => 'sometimes|array',
            'configuration' => 'sometimes|array',
        ]);

        $oldData = [
            'display_name' => $integration->display_name,
            'status' => $integration->status,
        ];

        if (isset($validated['display_name'])) {
            $integration->display_name = trim(strip_tags($validated['display_name']));
        }

        if (isset($validated['status'])) {
            $integration->status = $validated['status'];
        }

        if (isset($validated['credentials'])) {
            $integration->credentials = $validated['credentials'];
        }

        if (isset($validated['configuration'])) {
            $integration->configuration = $validated['configuration'];
        }

        $integration->save();

        return response()->json([
            'status' => 'success',
            'data' => $integration
        ]);
    }

    public function deleteIntegration(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        if ($integration->is_source_of_truth) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete source of truth integration. Please designate another integration as source of truth first.'
            ], 400);
        }
  
        $integration->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Integration deleted successfully.'
        ]);
    }

    public function getSyncLogs(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $limit = $request->input('limit', 50);
        $limit = min($limit, 100); 

        $syncLogs = $integration->syncLogs()
            ->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get();

        // Get the most recent sync
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

    public function healthCheck(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }
        
        $isHealthy = $integration->status === 'active';
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

    public function getRateLimitStatus(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

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

        // Get rate limits from integration config
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

    public function startHistoricalImport(Request $request, $id, LicenseChecker $licenseChecker)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $validated = $request->validate([
            'start_date' => 'required|date|before:today',
            'end_date' => 'required|date|after:start_date|before_or_equal:today',
        ]);

        $startDate = \Carbon\Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = \Carbon\Carbon::parse($validated['end_date'])->endOfDay();
        $requestedMonths = $startDate->diffInMonths($endDate);
        
        $retentionCheck = $licenseChecker->canExtendRetention($company, $requestedMonths);
        
        if (!$retentionCheck['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => $retentionCheck['reason'],
                'requested_months' => $requestedMonths
            ], 403);
        }

        // Check if there's actually a running job (not just the status flag)
        $runningJob = $integration->historicalImportJobs()
            ->whereIn('status', ['queued', 'running'])
            ->exists();
            
        if ($runningJob) {
            return response()->json([
                'status' => 'error',
                'message' => 'Historical import is already running for this integration. Please wait for it to complete or cancel it first.'
            ], 400);
        }
        
        $job = $integration->historicalImportJobs()->create([
            'company_id' => $company->company_id,
            'import_start_date' => $startDate,
            'import_end_date' => $endDate,
            'current_position' => $startDate,
            'status' => 'queued',
        ]);
        
        \App\Jobs\ImportHistoricalDataJob::dispatch($job, $startDate);
        
        $integration->update([
            'status' => 'importing_historical',
            'historical_import_status' => 'pending',
            'historical_import_progress' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Historical import started successfully.',
            'job_id' => $job->id
        ]);
    }
    
    public function browseSharePoint(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

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
    
    public function createSharePointFolder(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

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

        $validated = $request->validate([
            'path' => 'nullable|string',
            'name' => 'required|string|min:1'
        ]);

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

    public function getHistoricalImportJobs(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

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
}
