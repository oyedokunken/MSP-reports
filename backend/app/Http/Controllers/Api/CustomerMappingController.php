<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

    /**
     * Update customer details
     */
    public function updateCustomer(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $validated = $request->validate([
            'canonical_name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'report_destination' => 'sometimes|email|nullable',
            'metadata' => 'sometimes|array',
        ]);

        $oldData = [
            'canonical_name' => $customer->canonical_name,
            'status' => $customer->status,
            'report_destination' => $customer->report_destination,
        ];

        // Update fields if provided
        if (isset($validated['canonical_name'])) {
            $customer->canonical_name = trim(strip_tags($validated['canonical_name']));
        }

        if (isset($validated['status'])) {
            $customer->status = $validated['status'];
        }

        if (isset($validated['report_destination'])) {
            $customer->report_destination = $validated['report_destination'];
        }

        if (isset($validated['metadata'])) {
            $customer->metadata = array_merge($customer->metadata ?? [], $validated['metadata']);
        }

        $customer->save();

        // Log the update
        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'customer_updated',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Customer',
            'target_id' => $customer->customer_id,
            'event_data' => json_encode([
                'old_data' => $oldData,
                'new_data' => [
                    'canonical_name' => $customer->canonical_name,
                    'status' => $customer->status,
                    'report_destination' => $customer->report_destination,
                ],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $customer,
        ]);
    }

    /**
     * Merge two customers
     */
    public function mergeCustomers(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $primaryCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $id)
            ->first();

        if (!$primaryCustomer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Primary customer not found in your company.',
            ], 404);
        }

        $validated = $request->validate([
            'merge_customer_id' => 'required|integer|exists:customers,customer_id',
        ]);

        $mergeCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $validated['merge_customer_id'])
            ->first();

        if (!$mergeCustomer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer to merge not found in your company.',
            ], 404);
        }

        if ($primaryCustomer->customer_id === $mergeCustomer->customer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot merge a customer with itself.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Move all integration customer mappings to primary
            IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);

            // Move all report schedules to primary
            $mergeCustomer->reportSchedules()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            // Move all generated reports to primary
            $mergeCustomer->generatedReports()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            // Log the merge
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'customers_merged',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $primaryCustomer->customer_id,
                'event_data' => json_encode([
                    'primary_customer' => [
                        'id' => $primaryCustomer->customer_id,
                        'name' => $primaryCustomer->canonical_name,
                    ],
                    'merged_customer' => [
                        'id' => $mergeCustomer->customer_id,
                        'name' => $mergeCustomer->canonical_name,
                    ],
                    'integration_customers_moved' => IntegrationCustomer::where('customer_id', $primaryCustomer->customer_id)->count(),
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Delete the merged customer
            $mergeCustomer->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customers merged successfully',
                'data' => $primaryCustomer->load('integrationCustomers'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to merge customers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Split customer (reassign specific integration customers to new customer)
     */
    public function splitCustomer(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $originalCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $id)
            ->first();

        if (!$originalCustomer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $validated = $request->validate([
            'new_customer_name' => 'required|string|max:255',
            'integration_customer_ids' => 'required|array|min:1',
            'integration_customer_ids.*' => 'integer|exists:integration_customers,integration_customer_id',
        ]);

        DB::beginTransaction();
        try {
            // Create new customer
            $newCustomer = Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => trim(strip_tags($validated['new_customer_name'])),
                'status' => 'active',
                'report_destination' => $originalCustomer->report_destination,
                'metadata' => $originalCustomer->metadata,
            ]);

            // Move specified integration customer mappings to new customer
            $movedCount = CustomerMapping::whereIn('integration_customer_id', $validated['integration_customer_ids'])
                ->where('customer_id', $originalCustomer->customer_id)
                ->update(['customer_id' => $newCustomer->customer_id]);

            if ($movedCount === 0) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'No integration customers were moved. Check that they belong to the original customer.',
                ], 400);
            }

            // Log the split
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'customer_split',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $originalCustomer->customer_id,
                'event_data' => json_encode([
                    'original_customer' => [
                        'id' => $originalCustomer->customer_id,
                        'name' => $originalCustomer->canonical_name,
                    ],
                    'new_customer' => [
                        'id' => $newCustomer->customer_id,
                        'name' => $newCustomer->canonical_name,
                    ],
                    'integration_customers_moved' => $movedCount,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer split successfully',
                'data' => [
                    'original_customer' => $originalCustomer->load('integrationCustomers'),
                    'new_customer' => $newCustomer->load('integrationCustomers'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to split customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk map integration customers
     */
    public function bulkMapCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validate([
            'mappings' => 'required|array|min:1',
            'mappings.*.integration_customer_id' => 'required|integer|exists:integration_customers,integration_customer_id',
            'mappings.*.customer_id' => 'required_without:mappings.*.create_new|integer|exists:customers,customer_id',
            'mappings.*.create_new' => 'sometimes|boolean',
            'mappings.*.canonical_name' => 'required_if:mappings.*.create_new,true|string|max:255',
            'mappings.*.mapping_type' => 'sometimes|in:auto,manual',
            'mappings.*.notes' => 'sometimes|string|max:500',
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['mappings'] as $mapping) {
                $integrationCustomer = IntegrationCustomer::find($mapping['integration_customer_id']);

                // Verify company ownership
                if ($integrationCustomer->integration->company_id !== $company->company_id) {
                    $failedCount++;
                    $errors[] = "Integration customer {$mapping['integration_customer_id']} does not belong to your company";
                    continue;
                }

                // Create new customer or use existing
                if (!empty($mapping['create_new']) && $mapping['create_new'] === true) {
                    $customer = Customer::create([
                        'company_id' => $company->company_id,
                        'canonical_name' => trim(strip_tags($mapping['canonical_name'])),
                        'status' => 'active',
                    ]);
                } else {
                    $customer = Customer::where('company_id', $company->company_id)
                        ->where('customer_id', $mapping['customer_id'])
                        ->first();

                    if (!$customer) {
                        $failedCount++;
                        $errors[] = "Customer {$mapping['customer_id']} not found in your company";
                        continue;
                    }
                }

                // Create mapping
                CustomerMapping::create([
                    'customer_id' => $customer->customer_id,
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'mapping_type' => $mapping['mapping_type'] ?? 'manual',
                    'confidence_score' => ($mapping['mapping_type'] ?? 'manual') === 'manual' ? 100 : 0,
                    'mapped_by_user_id' => $authUser->user_id,
                    'mapped_at' => now(),
                    'notes' => $mapping['notes'] ?? null,
                ]);

                $successCount++;
            }

            // Log the bulk operation
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'bulk_customer_mapping',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'event_data' => json_encode([
                    'total_attempted' => count($validated['mappings']),
                    'successful' => $successCount,
                    'failed' => $failedCount,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'status' => $failedCount > 0 ? 'partial_success' : 'success',
                'message' => "Bulk mapping completed: {$successCount} successful, {$failedCount} failed",
                'data' => [
                    'successful' => $successCount,
                    'failed' => $failedCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk mapping failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a specific customer mapping
     */
    public function deleteMapping(Request $request, $customerId, $mappingId)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $mapping = CustomerMapping::where('customer_mapping_id', $mappingId)
            ->where('customer_id', $customerId)
            ->with('integrationCustomer')
            ->first();

        if (!$mapping) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mapping not found.',
            ], 404);
        }

        // Verify company ownership through integration
        if ($mapping->integrationCustomer->integration->company_id !== $company->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        // Log before deleting
        AuditLog::create([
            'company_id' => $company->company_id,
            'event_type' => 'customer_mapping_deleted',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'Customer',
            'target_id' => $customer->customer_id,
            'event_data' => json_encode([
                'customer_name' => $customer->canonical_name,
                'integration_customer_id' => $mapping->integration_customer_id,
                'integration_customer_name' => $mapping->integrationCustomer->name,
                'mapping_id' => $mapping->customer_mapping_id,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Delete the mapping
        $mapping->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mapping deleted successfully',
        ]);
    }
}
