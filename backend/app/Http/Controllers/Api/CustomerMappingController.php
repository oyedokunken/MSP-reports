<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\IntegrationCustomer;

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
                'canonical_name' => $customer->canonical_name,
                'status' => $customer->status,
                'report_destination' => $customer->report_destination,
                'integrations' => $customer->integrationCustomers->map(function ($ic) {
                    return [
                        'integration_customer_id' => $ic->integration_customer_id,
                        'integration_type' => $ic->integration->integration_type ?? null,
                        'display_name' => $ic->integration->display_name ?? null,
                        'external_id' => $ic->external_id,
                        'name' => $ic->name,
                        'active' => $ic->active,
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
                    'integration_type' => $ic->integration->integration_type ?? null,
                    'display_name' => $ic->integration->display_name ?? null,
                    'external_id' => $ic->external_id,
                    'name' => $ic->name,
                    'active' => $ic->active,
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
                'integration_type' => $ic->integration->integration_type ?? null,
                'display_name' => $ic->integration->display_name ?? null,
                'external_id' => $ic->external_id,
                'name' => $ic->name,
                'active' => $ic->active,
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

        $request->validate([
            'integration_customer_id' => 'required|exists:integration_customers,integration_customer_id',
            'customer_id' => 'required|exists:customers,customer_id',
        ]);

        $integrationCustomer = IntegrationCustomer::find($request->integration_customer_id);
        $customer = Customer::where('company_id', $company->company_id)
                            ->where('customer_id', $request->customer_id)
                            ->first();

        if (! $customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.'
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
                'canonical_name' => $customer->canonical_name,
            ],
        ]);
    }

    public function getCustomerIntegrations(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('company_id', $company->company_id)
                            ->where('customer_id', $id)
                            ->first();

        if (! $customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.'
            ], 404);
        }

        $integrations = $customer->integrationCustomers->map(function ($ic) {
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => $ic->integration->integration_type ?? null,
                'display_name' => $ic->integration->display_name ?? null,
                'external_id' => $ic->external_id,
                'name' => $ic->name,
                'active' => $ic->active,
                'last_seen_at' => $ic->last_seen_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $integrations,
        ]);
    }
}
