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
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    /**
     * Scan available integrations from filesystem
     */
    protected function scanAvailableIntegrations(): array
    {
        $integrationsPath = app_path('Integrations');
        $integrations = [];

        if (!is_dir($integrationsPath)) {
            return [];
        }

        $directories = array_filter(glob($integrationsPath . '/*'), 'is_dir');

        foreach ($directories as $dir) {
            $integrationName = basename($dir);
            
            // Skip Contracts directory
            if ($integrationName === 'Contracts') {
                continue;
            }

            $configFile = $dir . '/config.php';
            
            if (file_exists($configFile)) {
                $integrations[] = $integrationName;
            }
        }

        return $integrations;
    }

    /**
     * Get configuration for a specific integration type
     */
    protected function getIntegrationConfig(string $integrationType): ?array
    {
        $configFile = app_path("Integrations/{$integrationType}/config.php");
        
        if (!file_exists($configFile)) {
        }

        return require $configFile;
    }

    /**
     * Get all available integrations and configured integrations
     */
    public function getIntegrations(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = $this->scanAvailableIntegrations();
        
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
                    'next_sync_at' => $this->calculateNextSync($integration),
                    'records_synced' => $latestSync ? $latestSync->records_synced : 0,
                    'last_error' => $latestSync && $latestSync->status === 'failed' ? ($latestSync->error_messages[0] ?? null) : null,
                ];
            });

        return response()->json([
            'available_types' => $availableTypes,
            'configured' => $configuredIntegrations,
        ]);
    }

    private function calculateNextSync(Integration $integration): ?string
    {
        if (!$integration->last_sync_at || $integration->status !== 'active') {
            return null;
        }

        $syncSchedule = $integration->sync_schedule ?? $this->getDefaultSchedule($integration->integration_type);
        $intervalMinutes = $this->parseCronInterval($syncSchedule);
        
        if ($intervalMinutes === null) {
            return null;
        }

        return $integration->last_sync_at
            ->copy()
            ->addMinutes($intervalMinutes)
            ->toISOString();
    }

    private function parseCronInterval(string $cronSchedule): ?int
    {
        // Parse minute intervals (e.g., */5 * * * * = every 5 minutes)
        if (preg_match('/^\*\/(\d+)\s+\*/', $cronSchedule, $matches)) {
            return (int)$matches[1];
        }

        // Parse hour intervals (e.g., 0 */2 * * * = every 2 hours)
        if (preg_match('/^0\s+\*\/(\d+)/', $cronSchedule, $matches)) {
            return (int)$matches[1] * 60;
        }

        // Common patterns
        $patterns = [
            '0 */1 * * *' => 60,     // Hourly
            '0 */2 * * *' => 120,    // Every 2 hours
            '0 */4 * * *' => 240,    // Every 4 hours
            '0 */6 * * *' => 360,    // Every 6 hours
            '0 */12 * * *' => 720,   // Every 12 hours
            '0 0 * * *' => 1440,     // Daily
        ];

        return $patterns[$cronSchedule] ?? null;
    }

    private function getDefaultSchedule(string $integrationType): string
    {
        return match($integrationType) {
            'Datto' => '*/5 * * * *',       // Every 5 minutes
            'LibreNMS' => '*/10 * * * *',   // Every 10 minutes
            'Autotask' => '*/10 * * * *',   // Every 10 minutes
            'ITGlue' => '0 */6 * * *',      // Every 6 hours
            'SaaS', 'BitDefender', 'Sendmarc', 'Barracuda' => '0 */6 * * *', // Every 6 hours
            default => '0 */6 * * *',       // Default to 6 hours for others
        };
    }

    /**
     * Get configuration and credential fields for a specific integration type
     */
    public function getIntegrationTypeConfig(string $integrationType): JsonResponse
    {
        try {
            // Get credential fields from the integration class (new method)
            $className = "App\\Integrations\\{$integrationType}\\{$integrationType}Integration";
            if (!class_exists($className)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Integration type not found',
                ], 404);
            }

            // Create a temporary instance to get credential fields
            $tempIntegration = new \App\Models\Integration([
                'integration_type' => $integrationType,
                'credentials' => [],
            ]);
            $instance = new $className($tempIntegration);
            
            // Get metadata and credential fields
            $metadata = $instance->getMetadata();
            $credentialFields = $instance->getCredentialFields();

            return response()->json([
                'success' => true,
                'config' => [
                    'name' => $metadata['name'] ?? $integrationType,
                    'description' => $metadata['description'] ?? '',
                    'credential_fields' => $credentialFields,
                    'data_types' => $metadata['data_types'] ?? [],
                    'supports_historical' => $metadata['supports_historical'] ?? false,
                    'historical_range_days' => $metadata['historical_range_days'] ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get integration config', [
                'integration_type' => $integrationType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get integration configuration: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new integration
     */
    public function createIntegration(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = $this->scanAvailableIntegrations();
        
        $request->merge([
            'integration_type' => trim(strip_tags($request->integration_type)),
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

        $credentials = $validated['credentials'] ?? [];
        
        // Log integration creation attempt (credentials masked for security)
        Log::info('Integration credentials received', [
            'integration_type' => $validated['integration_type'],
            'credential_keys' => array_keys($credentials),
        ]);
        
        $credentials = array_filter($credentials, function($value) {
            return $value !== null && $value !== '';
        });
        
        Log::debug('Credentials filtered', [
            'credential_keys' => array_keys($credentials),
            'count' => count($credentials),
        ]);
        
        // Datto: map rmm_region to rmm_url, or use default if not provided
        if ($validated['integration_type'] === 'Datto') {
            if (isset($credentials['rmm_region'])) {
                $credentials['rmm_url'] = $credentials['rmm_region'];
                unset($credentials['rmm_region']);
            } elseif (!isset($credentials['rmm_url']) && (isset($credentials['rmm_api_key']) || isset($credentials['rmm_api_secret']))) {
                // If RMM credentials provided but no URL, use default Syrah region
                $credentials['rmm_url'] = 'https://syrah-api.centrastage.net';
            }
        }
        
        // LibreNMS: add default base_url if not provided, or use provided value
        if ($validated['integration_type'] === 'LibreNMS') {
            if (!isset($credentials['base_url']) && isset($credentials['api_token'])) {
                $credentials['base_url'] = 'http://192.168.228.7/api/v0';
                Log::debug('LibreNMS: Added default base_url');
            }
            // Ensure base_url doesn't have trailing slash
            if (isset($credentials['base_url'])) {
                $credentials['base_url'] = rtrim($credentials['base_url'], '/');
            }
        }
        
        if ($validated['integration_type'] === 'Autotask' && isset($credentials['zone'])) {
            $credentials['endpoint'] = $credentials['zone'];
            unset($credentials['zone']);
        }
        
        if ($validated['integration_type'] === 'Sendmarc' && !isset($credentials['base_url'])) {
            $credentials['base_url'] = 'https://portal.sendmarc.com/api/v1';
        }
        
        if ($validated['integration_type'] === 'Barracuda' && !isset($credentials['base_url'])) {
            $credentials['base_url'] = 'https://api.barracuda.com';
        }
        
        if ($validated['integration_type'] === 'BitDefender' && !isset($credentials['api_url'])) {
            $credentials['api_url'] = 'https://cloud.gravityzone.bitdefender.com/api/v1.0/jsonrpc';
        }

        // Test credentials before creating integration
        $tempIntegration = new Integration([
            'company_id' => $company->company_id,
            'integration_type' => $validated['integration_type'],
            'display_name' => $validated['display_name'],
            'status' => 'disconnected',
            'credentials' => $credentials,
            'configuration' => $validated['configuration'] ?? [],
        ]);

        if (!empty($credentials) && $validated['status'] !== 'disconnected') {
            try {
                $className = "App\\Integrations\\{$validated['integration_type']}\\{$validated['integration_type']}Integration";
                
                if (!class_exists($className)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Integration class not found.'
                    ], 500);
                }

                $instance = new $className($tempIntegration);
                $authResult = $instance->authenticate();

                if (!$authResult) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Authentication failed. Please verify your credentials and try again.'
                    ], 422);
                }

                $validated['status'] = 'active';
            } catch (\App\Integrations\Exceptions\AuthenticationException $e) {
                Log::error('Integration authentication failed', [
                    'integration_type' => $validated['integration_type'],
                    'status_code' => $e->getStatusCode(),
                    'error' => $e->getMessage(),
                    'response_body' => $e->getResponseBody(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $e->getUserMessage()
                ], 422);
            } catch (\Exception $e) {
                Log::error('Integration authentication failed', [
                    'integration_type' => $validated['integration_type'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication failed: ' . $e->getMessage()
                ], 422);
            }
        }

        $isFirstIntegration = $company->integrations()->count() === 0;
        $isSourceOfTruth = $isFirstIntegration ? true : ($validated['is_source_of_truth'] ?? false);
        $syncSchedule = match($validated['integration_type']) {
            'Autotask' => '*/10 * * * *',    // Every 10 minutes (companies + tickets + time)
            'Datto' => '*/5 * * * *',       // Every 5 minutes
            'ITGlue' => '0 */6 * * *',      // Every 6 hours
            'LibreNMS' => '*/10 * * * *',   // Every 10 minutes
            'SaaS', 'BitDefender', 'Sendmarc', 'Barracuda' => '0 */6 * * *',  // Every 6 hours
            default => '0 */6 * * *',       // Default to 6 hours for any others (safer than 4)
        };

        $integration = $company->integrations()->create([
            'integration_type' => $validated['integration_type'],
            'display_name' => $validated['display_name'],
            'status' => $validated['status'],
            'credentials' => $credentials,
            'configuration' => $validated['configuration'] ?? null,
            'is_source_of_truth' => $isSourceOfTruth,
            'sync_schedule' => $syncSchedule,
        ]);

        if ($isSourceOfTruth) {
            $settings = $company->settings ?? [];
            if (!isset($settings['integrations'])) {
                $settings['integrations'] = [];
            }
            $settings['integrations']['source_of_truth_integration_id'] = $integration->integration_id;
            $company->settings = $settings;
            $company->save();
        }

        // Log the creation
        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'integration_created',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Integration',
            'target_id' => $integration->integration_id,
            'event_data' => [
                'integration_type' => $integration->integration_type,
                'display_name' => $integration->display_name,
                'is_source_of_truth' => $isSourceOfTruth,
                'is_first_integration' => $isFirstIntegration,
            ],
            'ip_address' => $request->ip(),
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

        // Remove the entire restriction block to continue syncing other integrations (isolation)

        // if (!$integration->is_source_of_truth) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Manual sync is only allowed for the source of truth integration. Please sync the source of truth integration first, then other integrations will sync automatically.',
        //         'data' => [
        //             'integration_id' => $integration->integration_id,
        //             'integration_type' => $integration->integration_type,
        //             'display_name' => $integration->display_name,
        //             'is_source_of_truth' => false,
        //         ]
        //     ], 403);
        // }

        // Check for existing running/queued syncs to prevent overlap
        if ($integration->syncLogs()->whereIn('status', ['queued', 'running'])->exists()) {
             return response()->json([
                'status' => 'error',
                'message' => 'A sync is already in progress for this integration.'
            ], 409);
        }

        // Use SyncEngine to queue the sync (consistent with scheduled syncs)
        $syncEngine = app(\App\Services\SyncEngine::class);
        // Use 'high' priority for user-initiated syncs
        $syncLog = $syncEngine->triggerSync($integration, 'current_state', 'high');

        return response()->json([
            'status' => 'success',
            'message' => 'Sync queued successfully.',
            'data' => [
                'sync_log_id' => $syncLog->sync_id,
                'status' => $syncLog->status,
                'integration_id' => $integration->integration_id,
                'integration_type' => $integration->integration_type,
            ]
        ], 202); // 202 Accepted (async processing)
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

        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'integration_updated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Integration',
            'target_id' => $integration->integration_id,
            'event_data' => [
                'integration_type' => $integration->integration_type,
                'old_data' => $oldData,
                'new_data' => [
                    'display_name' => $integration->display_name,
                    'status' => $integration->status,
                ],
            ],
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
            'event_data' => [
                'integration_type' => $integration->integration_type,
                'display_name' => $integration->display_name,
                'customer_count' => $integration->integrationCustomers()->count(),
            ],
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
            'priority_level' => 1,
        ]);

        $integration->update([
            'historical_import_status' => 'running',
            'historical_import_progress' => 0,
        ]);

        // Dispatch ALL chunks upfront for parallel processing across multiple workers
        // Each chunk is 7 days (configured in ImportHistoricalDataJob)
        $chunkSize = 7; // Must match $daysToProcess in ImportHistoricalDataJob
        $currentChunkDate = $startDate->copy();
        $jobsDispatched = 0;
        
        while ($currentChunkDate->lte($endDate)) {
            \App\Jobs\ImportHistoricalDataJob::dispatch($job, $currentChunkDate->copy())
                ->onQueue('low')
                ->delay(now()->addSeconds($jobsDispatched * 2)); // Stagger by 2 seconds to avoid thundering herd
            
            $currentChunkDate->addDays($chunkSize);
            $jobsDispatched++;
        }
        
        // Update the job with total_jobs count for progress tracking
        $job->update(['total_jobs' => $jobsDispatched]);
        
        Log::info('Historical import jobs dispatched', [
            'integration_id' => $integration->integration_id,
            'total_jobs' => $jobsDispatched,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
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
                'total_jobs_dispatched' => $jobsDispatched,
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
