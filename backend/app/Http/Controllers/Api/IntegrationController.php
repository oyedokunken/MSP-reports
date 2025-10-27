<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Integration;
use App\Models\AuditLog;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function getIntegrations(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = [
            'Autotask',
            'Datto',
            'ConnectWise',
            'Microsoft365',
            'Zendesk',
        ];

        $configuredIntegrations = $company->integrations()
            ->withCount(['integrationCustomers as unmapped_customers_count' => function ($query) {
                $query->whereNull('customer_id');
            }])
            ->get(['integration_id', 'integration_type', 'display_name', 'status', 'is_source_of_truth', 'last_sync_at', 'last_sync_status'])
            ->map(function($integration) {
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
                ];
            });

        return response()->json([
            'available_types' => $availableTypes,
            'configured' => $configuredIntegrations,
        ]);
    }

    public function createIntegration(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = [
            'Autotask', 
            'Datto',
            'ConnectWise',
            'Microsoft365',
            'Zendesk',
        ];
        
        $request->merge([
            'integration_type' => trim($request->integration_type),
            'display_name' => trim(strip_tags($request->display_name)),
        ]);

        $validated = $request->validate([
            'integration_type' => ['required', 'string', Rule::in($availableTypes)],
            'display_name' => 'required|string|min:2|max:255|regex:/^[A-Za-z0-9\s\-\_\.]+$/',
            'status' => ['required', 'string', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
            'credentials' => 'nullable|array',
            'configuration' => 'nullable|array',
            'is_source_of_truth' => 'nullable|boolean',
        ]);

        $exists = $company->integrations()
            ->where('integration_type', $validated['integration_type'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration already exists for this company.'
            ], 409);
        }

        $sku = 'integration_' . strtolower($validated['integration_type']);

        $hasLicense = $company->licenses()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', now());
            })
            ->where(function ($q) use ($sku) {
                $q->where('product_sku', $sku)
                  ->orWhere('product_sku', 'unlimited')
                  ->orWhere('license_type', 'unlimited');
            })
            ->exists();

        if (! $hasLicense) {
            return response()->json([
                'status' => 'error',
                'message' => 'Company does not have an active license for this integration type.'
            ], 403);
        }

        $integration = $company->integrations()->create([
            'integration_type' => $validated['integration_type'],
            'display_name' => $validated['display_name'],
            'status' => $validated['status'],
            'credentials' => $validated['credentials'] ?? null,
            'configuration' => $validated['configuration'] ?? null,
            'is_source_of_truth' => $validated['is_source_of_truth'] ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $integration
        ], 201);
    }

    public function triggerSync(Request $request, $id)
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

        if (!$integration->is_source_of_truth) {
            return response()->json([
                'status' => 'error',
                'message' => 'Manual sync is only allowed for the source of truth integration. Please sync the source of truth integration first, then other integrations will sync automatically.',
                'data' => [
                    'integration_id' => $integration->integration_id,
                    'integration_type' => $integration->integration_type,
                    'display_name' => $integration->display_name,
                    'is_source_of_truth' => false,
                ]
            ], 403);
        }

        // Create sync log
        $syncLog = $integration->syncLogs()->create([
            'sync_type' => 'current_state',
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            // Get the integration class
            $integrationClass = "App\\Integrations\\{$integration->integration_type}\\{$integration->integration_type}Integration";
            
            if (!class_exists($integrationClass)) {
                throw new \Exception("Integration class not found: {$integrationClass}");
            }

            // Instantiate and call sync
            $integrationInstance = new $integrationClass($integration);
            $result = $integrationInstance->sync();

            // Update sync log with success
            $syncLog->update([
                'completed_at' => now(),
                'status' => 'success',
                'records_synced' => $result['records_synced'] ?? 0,
                'records_failed' => $result['records_failed'] ?? 0,
                'rate_limit_hits' => $result['rate_limit_hits'] ?? 0,
                'customers_mapped' => $result['customers_mapped'] ?? [],
            ]);

            $integration->update([
                'last_sync_at' => $syncLog->completed_at,
                'last_sync_status' => 'success',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sync completed successfully.',
                'data' => [
                    'integration' => $integration->fresh(),
                    'sync_log' => $syncLog,
                    'result' => $result,
                ]
            ], 200);

        } catch (\Exception $e) {
            // Update sync log with failure
            $syncLog->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_messages' => [$e->getMessage()],
            ]);

            $integration->update([
                'last_sync_at' => $syncLog->completed_at,
                'last_sync_status' => 'failed',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage(),
                'data' => [
                    'integration' => $integration->fresh(),
                    'sync_log' => $syncLog,
                ]
            ], 500);
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

        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'integration_updated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Integration',
            'target_id' => $integration->integration_id,
            'event_data' => json_encode([
                'integration_type' => $integration->integration_type,
                'old_data' => $oldData,
                'new_data' => [
                    'display_name' => $integration->display_name,
                    'status' => $integration->status,
                ],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

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
  
        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'integration_deleted',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Integration',
            'target_id' => $integration->integration_id,
            'event_data' => json_encode([
                'integration_type' => $integration->integration_type,
                'display_name' => $integration->display_name,
                'customer_count' => $integration->integrationCustomers()->count(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

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

        $limits = [
            'Autotask' => ['per_minute' => 100, 'per_hour' => 5000],
            'Datto' => ['per_minute' => 60, 'per_hour' => 1000],
            'ConnectWise' => ['per_minute' => 120, 'per_hour' => 6000],
            'Microsoft365' => ['per_minute' => 200, 'per_hour' => 10000],
            'Zendesk' => ['per_minute' => 100, 'per_hour' => 5000],
        ];

        $limit = $limits[$integration->integration_type] ?? ['per_minute' => 60, 'per_hour' => 1000];

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

        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $requestedMonths = $startDate->diffInMonths($endDate);
        
        $retentionCheck = $licenseChecker->canExtendRetention($company, $requestedMonths);
        
        if (!$retentionCheck['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => $retentionCheck['reason'],
                'requested_months' => $requestedMonths
            ], 403);
        }

        if ($integration->historical_import_status === 'running') {
            return response()->json([
                'status' => 'error',
                'message' => 'Historical import is already running for this integration.'
            ], 400);
        }
        
        $job = $integration->historicalImportJobs()->create([
            'company_id' => $company->company_id,
            'import_start_date' => $validated['start_date'],
            'import_end_date' => $validated['end_date'],
            'current_position' => $validated['start_date'],
            'status' => 'queued',
            'priority_level' => 1,
        ]);

        $integration->update([
            'historical_import_status' => 'running',
            'historical_import_progress' => 0,
        ]);

        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'historical_import_started',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Integration',
            'target_id' => $integration->integration_id,
            'event_data' => json_encode([
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'job_id' => $job->id,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Historical import started successfully',
            'data' => [
                'job_id' => $job->id,
                'integration' => $integration,
            ]
        ]);
    }
}
