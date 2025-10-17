<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\IntegrationCustomer;
use Illuminate\Support\Facades\Validator;

class CustomerMappingController extends Controller
{
    public function getCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customers = Customer::with([
            'integrationCustomers.integration:integration_id,display_name,integration_type'
        ])
            ->where('company_id', $company->company_id)
            ->get();

        $unmappedIntegrationCustomers = IntegrationCustomer::with(
            'integration:integration_id,display_name,integration_type'
        )
            ->whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->whereNull('customer_id')
            ->get();

        $merged = $customers->map(function ($customer) {
            return [
                'customer_id' => $customer->customer_id,
                'canonical_name' => e($customer->canonical_name),
                'status' => e($customer->status),
                'report_destination' => e($customer->report_destination),
                'integrations' => $customer->integrationCustomers->map(function ($ic) {
                    return [
                        'integration_customer_id' => $ic->integration_customer_id,
                        'integration_type' => e($ic->integration->integration_type ?? null),
                        'display_name' => e($ic->integration->display_name ?? null),
                        'external_id' => e($ic->external_id),
                        'name' => e($ic->name),
                        'active' => (bool) $ic->active,
                        'last_seen_at' => $ic->last_seen_at,
                    ];
                }),
            ];
        });

        foreach ($unmappedIntegrationCustomers as $ic) {
            $merged->push([
                'customer_id' => null,
                'canonical_name' => null,
                'status' => null,
                'report_destination' => null,
                'integrations' => [[
                    'integration_customer_id' => $ic->integration_customer_id,
                    'integration_type' => e($ic->integration->integration_type ?? null),
                    'display_name' => e($ic->integration->display_name ?? null),
                    'external_id' => e($ic->external_id),
                    'name' => e($ic->name),
                    'active' => (bool) $ic->active,
                    'last_seen_at' => $ic->last_seen_at,
                ]],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $merged->values(),
        ]);
    }

    public function getUnmappedCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $unmappedIntegrationCustomers = IntegrationCustomer::with(
            'integration:integration_id,display_name,integration_type'
        )
            ->whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->whereNull('customer_id')
            ->get();

        $data = $unmappedIntegrationCustomers->map(function ($ic) {
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => e($ic->name),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function mapCustomer(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validator = Validator::make($request->all(), [
            'integration_customer_id' => 'required|integer|exists:integration_customers,integration_customer_id',
            'customer_id' => 'required|integer|exists:customers,customer_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integrationCustomer = IntegrationCustomer::find($request->integration_customer_id);

        // Ensure the integration belongs to the same company
        if ($integrationCustomer->integration->company_id !== $company->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration customer does not belong to your company.',
            ], 403);
        }

        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $request->customer_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $integrationCustomer->customer_id = $customer->customer_id;
        $integrationCustomer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Integration customer mapped successfully.',
            'data' => [
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'customer_id' => $customer->customer_id,
                'canonical_name' => e($customer->canonical_name),
            ],
        ]);
    }

    public function getCustomerIntegrations(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        if (!is_numeric($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid customer ID format.',
            ], 400);
        }

        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $integrations = $customer->integrationCustomers->map(function ($ic) {
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => e($ic->name),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $integrations,
        ]);
    }
}
