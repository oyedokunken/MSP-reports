<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CustomerMappingService;
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

        // Get pagination parameters
        $perPage = 20;
        $page = $request->input('page', 1);

        // Get all customers for this company
        $customers = Customer::with([
            'integrationCustomers.integration:integration_id,display_name,integration_type',
            'reportSchedules'
        ])
            ->where('company_id', $company->company_id)
            ->get();

        // Get all unmapped integration customers
        $unmappedIntegrationCustomers = IntegrationCustomer::with(
            'integration:integration_id,display_name,integration_type'
        )
            ->whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->whereNull('customer_id')
            ->get();

        // Map customers to response format
        $merged = $customers->map(function ($customer) {
            return [
                'customer_id' => $customer->customer_id,
                'canonical_name' => e($customer->canonical_name),
                'status' => e($customer->status),
                'report_destination' => e($customer->report_destination),
                'report_schedules_count' => $customer->reportSchedules->count(),
                'integrations' => $customer->integrationCustomers->map(function ($ic) {
                    $mapping = $ic->customerMappings()->first();
                    
                    return [
                        'integration_customer_id' => $ic->integration_customer_id,
                        'integration_type' => e($ic->integration->integration_type ?? null),
                        'display_name' => e($ic->integration->display_name ?? null),
                        'external_id' => e($ic->external_id),
                        'name' => e($ic->name),
                        'active' => (bool) $ic->active,
                        'last_seen_at' => $ic->last_seen_at,
                        'mapping' => $mapping ? [
                            'type' => $mapping->mapping_type,
                            'confidence_score' => $mapping->confidence_score,
                            'mapped_at' => $mapping->mapped_at,
                            'notes' => $mapping->notes,
                        ] : null,
                    ];
                }),
            ];
        });

        // Add unmapped integration customers
        foreach ($unmappedIntegrationCustomers as $ic) {
            $merged->push([
                'customer_id' => null,
                'canonical_name' => null,
                'status' => null,
                'report_destination' => null,
                'report_schedules_count' => 0,
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

        // Calculate pagination
        $total = $merged->count();
        $lastPage = (int) ceil($total / $perPage);
        $from = ($page - 1) * $perPage;
        
        // Slice the collection for current page
        $paginatedData = $merged->slice($from, $perPage)->values();

        return response()->json([
            'status' => 'success',
            'data' => $paginatedData,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $from + 1 : null,
                'to' => $total > 0 ? min($from + $perPage, $total) : null,
            ],
        ]);
    }

    public function getUnmappedCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $unmappedIntegrationCustomers = IntegrationCustomer::with([
            'integration:integration_id,company_id,display_name,integration_type'
        ])
            ->whereHas('integration', function ($q) use ($company) {
                $q->where('company_id', $company->company_id);
            })
            ->whereNull('customer_id')
            ->get();

        $countsByIntegrationType = $unmappedIntegrationCustomers
            ->groupBy(fn($ic) => $ic->integration->integration_type ?? 'Unknown')
            ->map(fn($group) => $group->count())
            ->toArray();

        $data = $unmappedIntegrationCustomers->map(function ($ic) use ($company) {
            $mappingService = app(CustomerMappingService::class);
            $suggestions = $mappingService->getSuggestedMatches($ic, 3);
            
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => e($ic->name),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at,
                'suggested_matches' => array_map(function($suggestion) {
                    return [
                        'customer_id' => $suggestion['customer']->customer_id,
                        'canonical_name' => $suggestion['customer']->canonical_name,
                        'confidence_score' => $suggestion['confidence_score'],
                    ];
                }, $suggestions),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'summary' => [
                'total_unmapped' => $unmappedIntegrationCustomers->count(),
                'by_integration_type' => $countsByIntegrationType,
            ],
        ]);
    }

    public function mapCustomer(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Validate based on whether we're creating new or mapping to existing
        $rules = [
            'integration_customer_id' => 'required|integer|exists:integration_customers,integration_customer_id',
            'create_new' => 'sometimes|boolean',
        ];

        // If not creating new, customer_id is required
        if (!$request->has('create_new') || !$request->create_new) {
            $rules['customer_id'] = 'required|integer|exists:customers,customer_id';
        } else {
            // If creating new, canonical_name is required
            $rules['canonical_name'] = 'required|string|max:255';
            $rules['status'] = 'sometimes|in:active,inactive';
            $rules['report_destination'] = 'sometimes|email';
            $rules['metadata'] = 'sometimes|array';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $integrationCustomer = IntegrationCustomer::find($request->integration_customer_id);

        // Verify tenant isolation
        if ($integrationCustomer->integration->company_id !== $company->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration customer does not belong to your company.',
            ], 403);
        }

        // Check if already mapped
        if ($integrationCustomer->customer_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration customer is already mapped.',
                'data' => [
                    'current_customer_id' => $integrationCustomer->customer_id,
                    'current_canonical_name' => $integrationCustomer->customer->canonical_name ?? null,
                ],
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Option 1: Create new unified customer
            if ($request->create_new) {
                $customer = Customer::create([
                    'company_id' => $company->company_id,
                    'canonical_name' => $request->canonical_name,
                    'status' => $request->status ?? 'active',
                    'report_destination' => $request->report_destination,
                    'metadata' => $request->metadata ?? [],
                ]);

                $mappingType = 'manual';
                $message = 'New customer created and integration customer mapped successfully.';
            } 
            // Option 2: Map to existing customer
            else {
                $customer = Customer::where('company_id', $company->company_id)
                    ->where('customer_id', $request->customer_id)
                    ->first();

                if (!$customer) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Customer not found in your company.',
                    ], 404);
                }

                $mappingType = 'manual';
                $message = 'Integration customer mapped successfully.';
            }

            // Map the integration customer to the unified customer
            $integrationCustomer->customer_id = $customer->customer_id;
            $integrationCustomer->save();

            // Create customer mapping record for audit trail
            CustomerMapping::create([
                'customer_id' => $customer->customer_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
                'mapping_type' => $mappingType,
                'confidence_score' => 100, // Manual mapping = 100% confidence
                'mapped_by_user_id' => $authUser->user_id,
                'mapped_at' => now(),
                'notes' => $request->create_new ? 'New customer created via manual mapping' : 'Manually mapped to existing customer',
            ]);

            // Log to audit trail
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => $request->create_new ? 'customer_created_and_mapped' : 'customer_mapped',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $customer->customer_id,
                'event_data' => [
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'integration_type' => $integrationCustomer->integration->integration_type,
                    'external_id' => $integrationCustomer->external_id,
                    'canonical_name' => $customer->canonical_name,
                    'created_new' => $request->create_new ?? false,
                ],
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'integration_customer_id' => $integrationCustomer->integration_customer_id,
                    'customer_id' => $customer->customer_id,
                    'canonical_name' => e($customer->canonical_name),
                    'created_new' => $request->create_new ?? false,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to map customer: ' . $e->getMessage(),
            ], 500);
        }
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
            $mapping = $ic->customerMappings()->first();
            
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => e($ic->name),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at,
                'mapping' => $mapping ? [
                    'type' => $mapping->mapping_type,
                    'confidence_score' => $mapping->confidence_score,
                    'mapped_at' => $mapping->mapped_at,
                    'notes' => $mapping->notes,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $integrations,
        ]);
    }

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
            
            IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);

            
            $mergeCustomer->reportSchedules()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            
            $mergeCustomer->generatedReports()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            
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
            
            $newCustomer = Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => trim(strip_tags($validated['new_customer_name'])),
                'status' => 'active',
                'report_destination' => $originalCustomer->report_destination,
                'metadata' => $originalCustomer->metadata,
            ]);

            
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

        
        if ($mapping->integrationCustomer->integration->company_id !== $company->company_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        
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

        
        $mapping->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mapping deleted successfully',
        ]);
    }
}
