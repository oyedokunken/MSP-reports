<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Integration;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function getIntegrations(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $availableTypes = [
            'Datto',
            'Autotask',
            'ConnectWise',
            'Microsoft365',
            'Zendesk',
        ];

        $configuredIntegrations = $company->integrations()
            ->get(['integration_id', 'integration_type', 'display_name', 'status']);

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
            'Datto',
            'Autotask',
            'ConnectWise',
            'Microsoft365',
            'Zendesk',
        ];

        $validated = $request->validate([
            'integration_type' => ['required', Rule::in($availableTypes)],
            'display_name' => 'required|string|max:255',
            'status' => ['required', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
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
            ->where('product_sku', $sku)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>', now());
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

        $integration = $company->integrations()->where('integration_id', $id)->first();

        if (! $integration) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration not found for this company.'
            ], 404);
        }

        $syncLog = $integration->syncLogs()->create([
            'sync_type' => 'current_state',
            'started_at' => now(),
            'status' => 'running',
        ]);

        sleep(1);

        $syncLog->update([
            'completed_at' => now(),
            'status' => 'success',
            'records_synced' => rand(10, 100),
            'records_failed' => 0,
            'rate_limit_hits' => rand(0, 2),
        ]);

        $integration->update([
            'last_sync_at' => $syncLog->completed_at,
            'last_sync_status' => $syncLog->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Manual sync completed successfully.',
            'data' => [
                'integration' => $integration,
                'sync_log' => $syncLog,
            ]
        ], 200);
    }

    public function getIntegrationCustomers(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $integration = $company->integrations()
            ->where('integration_id', $id)
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

}
