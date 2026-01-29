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
            // Get company and customer context from API key middleware
            $companyId = $request->input('_company_id');
            $customerId = $request->input('_customer_id');
            
            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API key context. Company ID not found.',
                ], 401);
            }
            
            // Require customer context for Active Directory agent keys
            if (!$customerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active Directory sync requires a customer-scoped API key. Please use an API key created for a specific customer.',
                ], 403);
            }
            
            // Validate request payload
            $validated = $request->validate([
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

            DB::beginTransaction();
            
            // Get or create single Active Directory integration for this company
            $integration = $this->syncService->getOrCreateIntegration($companyId);
            
            // Create sync log
            $syncLog = SyncLog::create([
                'integration_id' => $integration->integration_id,
                'sync_type' => $validated['syncType'],
                'started_at' => now(),
                'status' => 'running',
            ]);
            
            // Process users (with customer context)
            $usersProcessed = $this->syncService->processUsers(
                $integration,
                $customerId,
                $validated['data']['users']
            );
            
            // Process groups (with customer context)
            $groupsProcessed = $this->syncService->processGroups(
                $integration,
                $customerId,
                $validated['data']['securityGroups']
            );
            
            // Process memberships (with customer context)
            $membershipsProcessed = $this->syncService->processMemberships(
                $integration,
                $customerId,
                $validated['data']['groupMemberships']
            );
            
            // Process license assignments (SPLA)
            $licenseAssignmentsProcessed = 0;
            if (!empty($validated['data']['licenseAssignments'])) {
                $licenseAssignmentsProcessed = $this->syncService->processLicenseAssignments(
                    $integration,
                    $customerId,
                    $validated['data']['licenseAssignments']
                );
            }
            
            // Clean up orphans for this customer only (on full sync)
            $orphansRemoved = $this->syncService->cleanupOrphans(
                $integration,
                $customerId,
                $validated['syncType']
            );
            
            // Update sync log
            $syncLog->update([
                'completed_at' => now(),
                'status' => 'success',
                'records_synced' => $usersProcessed + $groupsProcessed + $membershipsProcessed + $licenseAssignmentsProcessed,
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
                'target_id' => $integration->integration_id,
                'event_data' => [
                    'sync_type' => $validated['syncType'],
                    'users_processed' => $usersProcessed,
                    'groups_processed' => $groupsProcessed,
                    'memberships_processed' => $membershipsProcessed,
                    'license_assignments_processed' => $licenseAssignmentsProcessed,
                    'domain' => $validated['domain'],
                    'duration_seconds' => round(microtime(true) - $startTime, 2),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            DB::commit();
            
            Log::info('Active Directory sync completed successfully', [
                'company_id' => $integration->company_id,
                'integration_id' => $integration->integration_id,
                'sync_type' => $validated['syncType'],
                'users' => $usersProcessed,
                'groups' => $groupsProcessed,
                'memberships' => $membershipsProcessed,
                'license_assignments' => $licenseAssignmentsProcessed,
                'duration' => round(microtime(true) - $startTime, 2),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Active Directory data synced successfully',
                'data' => [
                    'integrationId' => $integration->integration_id,
                    'syncLogId' => $syncLog->sync_id,
                    'usersProcessed' => $usersProcessed,
                    'groupsProcessed' => $groupsProcessed,
                    'membershipsProcessed' => $membershipsProcessed,
                    'licenseAssignmentsProcessed' => $licenseAssignmentsProcessed,
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
                'company_id' => $companyId,
                'customer_id' => $customerId,
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

    /**
     * Get Active Directory integration status for the authenticated company/customer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        $companyId = $request->input('_company_id');
        $customerId = $request->input('_customer_id');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required - company context not found',
            ], 401);
        }

        try {
            $integration = $this->syncService->getOrCreateIntegration($companyId);
            
            // Get sync statistics for this customer (if customer-scoped key)
            $stats = $this->syncService->getCustomerStatus($integration, $customerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'integration_id' => $integration->integration_id,
                    'integration_status' => $integration->status,
                    'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                    'last_sync_status' => $integration->last_sync_status,
                    'error_message' => $integration->error_message,
                    'customer_id' => $customerId,
                    'customer_stats' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Active Directory status', [
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
