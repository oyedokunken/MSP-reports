<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ActiveDirectorySyncService;
use App\Models\SyncLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ActiveDirectoryController extends Controller
{
    protected ActiveDirectorySyncService $syncService;

    public function __construct(ActiveDirectorySyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Receive Active Directory sync push from agent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function push(Request $request)
    {
        $startTime = microtime(true);
        
        try {
            // Validate request payload
            $validated = $request->validate([
                'companyId' => 'required|integer|exists:companies,id',
                'integrationId' => 'nullable|integer|exists:integrations,id',
                'syncType' => 'required|in:full,delta',
                'syncTimestamp' => 'required|date',
                'domain' => 'required|string|max:255',
                'data' => 'required|array',
                'data.users' => 'required|array',
                'data.securityGroups' => 'required|array',
                'data.groupMemberships' => 'required|array',
                'statistics' => 'required|array',
                'statistics.userCount' => 'required|integer',
                'statistics.groupCount' => 'required|integer',
                'statistics.membershipCount' => 'required|integer',
            ]);

            // Verify company context matches API key's company
            // API key middleware injects _company_id into request
            $apiKeyCompanyId = $request->input('_company_id');
            
            if ($apiKeyCompanyId && $validated['companyId'] != $apiKeyCompanyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company ID mismatch. API key belongs to a different company.',
                ], 403);
            }

            DB::beginTransaction();
            
            // Get or create integration
            $integration = $this->syncService->getOrCreateIntegration(
                $validated['companyId'],
                $validated['domain']
            );
            
            // Create sync log
            $syncLog = SyncLog::create([
                'integration_id' => $integration->id,
                'sync_type' => $validated['syncType'],
                'started_at' => now(),
                'status' => 'running',
            ]);
            
            // Process users
            $usersProcessed = $this->syncService->processUsers(
                $integration,
                $validated['data']['users']
            );
            
            // Process groups
            $groupsProcessed = $this->syncService->processGroups(
                $integration,
                $validated['data']['securityGroups']
            );
            
            // Process memberships
            $membershipsProcessed = $this->syncService->processMemberships(
                $integration,
                $validated['data']['groupMemberships']
            );
            
            // Clean up orphans (only on full sync)
            $orphansRemoved = $this->syncService->cleanupOrphans(
                $integration,
                $validated['syncType']
            );
            
            // Update sync log
            $syncLog->update([
                'completed_at' => now(),
                'status' => 'success',
                'records_synced' => $usersProcessed + $groupsProcessed + $membershipsProcessed,
                'records_failed' => 0,
                'customers_mapped' => 0, // Not applicable for AD
            ]);
            
            // Update integration status
            $integration->update([
                'status' => 'active',
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'error_message' => null,
            ]);
            
            // Create audit log
            AuditLog::create([
                'company_id' => $integration->company_id,
                'event_type' => 'active_directory_sync',
                'actor_type' => 'System',
                'actor_id' => null,
                'target_type' => 'Integration',
                'target_id' => $integration->id,
                'event_data' => [
                    'sync_type' => $validated['syncType'],
                    'users_processed' => $usersProcessed,
                    'groups_processed' => $groupsProcessed,
                    'memberships_processed' => $membershipsProcessed,
                    'domain' => $validated['domain'],
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            DB::commit();
            
            Log::info('Active Directory sync completed successfully', [
                'company_id' => $integration->company_id,
                'integration_id' => $integration->id,
                'sync_type' => $validated['syncType'],
                'users' => $usersProcessed,
                'groups' => $groupsProcessed,
                'memberships' => $membershipsProcessed,
                'duration' => round(microtime(true) - $startTime, 2),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Active Directory data synced successfully',
                'data' => [
                    'integrationId' => $integration->id,
                    'syncLogId' => $syncLog->id,
                    'usersProcessed' => $usersProcessed,
                    'groupsProcessed' => $groupsProcessed,
                    'membershipsProcessed' => $membershipsProcessed,
                    'orphansRemoved' => $orphansRemoved,
                    'timestamp' => now()->toIso8601String(),
                    'durationSeconds' => round(microtime(true) - $startTime, 2),
                ],
            ]);
            
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Active Directory sync failed', [
                'company_id' => $validated['companyId'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Update sync log if it was created
            if (isset($syncLog)) {
                $syncLog->update([
                    'completed_at' => now(),
                    'status' => 'failed',
                    'error_messages' => [$e->getMessage()],
                ]);
            }
            
            // Update integration status if it exists
            if (isset($integration)) {
                $integration->update([
                    'status' => 'error',
                    'last_sync_status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process Active Directory data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
