<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExchangeSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class ExchangeController extends Controller
{
    protected ExchangeSyncService $syncService;

    public function __construct(ExchangeSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle Exchange data push from agent
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function push(Request $request): JsonResponse
    {
        // Get company and customer from API key middleware
        $companyId = $request->get('_company_id');
        $customerId = $request->get('_customer_id');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'error' => 'Company context not found'
            ], 401);
        }

        if (!$customerId) {
            return response()->json([
                'success' => false,
                'error' => 'Customer-scoped API key required for Exchange sync'
            ], 403);
        }

        // Validate request payload
        $validator = Validator::make($request->all(), [
            'syncType' => 'required|in:full,delta',
            'syncTimestamp' => 'required|date',
            'exchangeOrganization' => 'nullable|string',
            'exchangeVersion' => 'nullable|string',
            'data' => 'required|array',
            'data.mailboxes' => 'required|array',
            'data.emailAliases' => 'nullable|array',
            'data.permissions' => 'nullable|array',
            'data.statistics' => 'nullable|array',
            'statistics' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            Log::warning('Exchange push validation failed', [
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            // Get or create Exchange integration for this company
            $integration = $this->syncService->getOrCreateIntegration($companyId);

            // Process the sync data
            $result = $this->syncService->processSyncData(
                integration: $integration,
                customerId: $customerId,
                syncType: $request->input('syncType'),
                data: $request->input('data'),
                metadata: [
                    'sync_timestamp' => $request->input('syncTimestamp'),
                    'exchange_organization' => $request->input('exchangeOrganization'),
                    'exchange_version' => $request->input('exchangeVersion'),
                    'agent_statistics' => $request->input('statistics', []),
                ]
            );

            Log::info('Exchange sync completed', [
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'integration_id' => $integration->integration_id,
                'sync_type' => $request->input('syncType'),
                'processed' => $result,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Exchange data synced successfully',
                'integration_id' => $integration->integration_id,
                'processed' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Exchange sync failed', [
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Exchange integration status for the authenticated company/customer
     */
    public function status(Request $request): JsonResponse
    {
        $companyId = $request->get('_company_id');
        $customerId = $request->get('_customer_id');

        if (!$companyId || !$customerId) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        try {
            $integration = $this->syncService->getOrCreateIntegration($companyId);
            $status = $this->syncService->getCustomerStatus($integration, $customerId);

            return response()->json([
                'success' => true,
                'integration_id' => $integration->integration_id,
                'customer_id' => $customerId,
                'status' => $status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
