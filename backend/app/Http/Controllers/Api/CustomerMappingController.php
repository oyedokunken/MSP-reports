<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CustomerMappingService;
use App\Models\Customer;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\IntegrationData;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerMappingController extends Controller
{
    public function getCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Get pagination and filter parameters
        $perPage = 15;
        $search = $request->input('search');
        $statuses = $request->input('statuses');
        $reportFilters = $request->input('report_filters');
        $integrationTypes = $request->input('integration_types');
        $sourceOfTruthOnly = $request->input('source_of_truth_only');

        // OPTIMIZED: Server-side filtering and pagination
        // Load ALL integrations for each customer (no filtering in eager load)
        $query = Customer::with([
            'integrationCustomers.integration:integration_id,display_name,integration_type,is_source_of_truth',
            'integrationCustomers.customerMappings',
            'reportSchedules'
        ])
            ->where('company_id', $company->company_id);

        // Apply search filter
        if ($search) {
            $query->where('canonical_name', 'ILIKE', '%' . $search . '%');
        }

        // Apply status filter
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        // Apply report filter
        if (!empty($reportFilters)) {
            // If both are selected, we show all (so no filter needed)
            // If only one is selected, apply that filter
            if (in_array('configured', $reportFilters) && !in_array('not-configured', $reportFilters)) {
                $query->has('reportSchedules');
            } elseif (in_array('not-configured', $reportFilters) && !in_array('configured', $reportFilters)) {
                $query->doesntHave('reportSchedules');
            }
        }

        // Apply integration type filter
        if (!empty($integrationTypes)) {
            $query->whereHas('integrationCustomers.integration', function ($q) use ($integrationTypes) {
                $q->whereIn('integration_type', $integrationTypes);
            });
        }

        // Apply source of truth filter
        if ($sourceOfTruthOnly !== null) {
            $isSourceOfTruth = filter_var($sourceOfTruthOnly, FILTER_VALIDATE_BOOLEAN);
            
            if ($isSourceOfTruth) {
                // Show customers that have at least one SOT integration OR are static customers
                $query->where(function ($q) {
                    $q->whereHas('integrationCustomers.integration', function ($subQ) {
                        $subQ->where('is_source_of_truth', true);
                    })->orWhere('is_static', true);
                });
            } else {
                // Show customers that have ONLY non-SOT integrations (exclude customers with any SOT integration)
                // Also exclude static customers from secondary list
                $query->whereHas('integrationCustomers.integration', function ($q) {
                    $q->where('is_source_of_truth', false);
                })->whereDoesntHave('integrationCustomers.integration', function ($q) {
                    $q->where('is_source_of_truth', true);
                })->where('is_static', false);
            }
        }

        $customers = $query->orderBy('canonical_name', 'asc')
            ->paginate($perPage);

        // Map customers to response format
        $data = $customers->map(function ($customer) {
            return [
                'customer_id' => $customer->customer_id,
                'canonical_name' => $customer->canonical_name,
                'status' => e($customer->status),
                'is_static' => $customer->is_static,
                'report_destination' => e($customer->report_destination),
                'report_schedules_count' => $customer->reportSchedules->count(),
                'integrations' => $customer->integrationCustomers->map(function ($ic) {
                    // Use eager-loaded relationship instead of query
                    $mapping = $ic->customerMappings->first();
                    
                    return [
                        'integration_customer_id' => $ic->integration_customer_id,
                        'integration_type' => e($ic->integration->integration_type ?? null),
                        'display_name' => e($ic->integration->display_name ?? null),
                        'external_id' => e($ic->external_id),
                        'name' => html_entity_decode($ic->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'active' => (bool) $ic->active,
                        'last_seen_at' => $ic->last_seen_at ? (int)($ic->last_seen_at->timestamp * 1000) : null,
                        'mapping' => $mapping ? [
                            'type' => $mapping->mapping_type,
                            'confidence_score' => $mapping->confidence_score,
                            'mapped_at' => $mapping->mapped_at,
                            'notes' => $mapping->notes,
                        ] : null,   
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
            'meta' => [
                'scoped_to_customers' => $authUser->scoped_customers,
                'is_scoped_user' => !empty($authUser->scoped_customers),
            ],
        ]);
    }

    public function getUnmappedCustomers(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Get pagination and search parameters
        $perPage = 15;
        $page = $request->input('page', 1);
        $search = $request->input('search');

        // Base query for unmapped customers
        $query = IntegrationCustomer::whereHas('integration', function ($q) use ($company) {
            $q->where('company_id', $company->company_id);
        })
            ->whereNull('customer_id');

        // Apply search filter if provided
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', '%' . $search . '%')
                  ->orWhere('external_id', 'ILIKE', '%' . $search . '%')
                  ->orWhereHas('integration', function($innerQ) use ($search) {
                      $innerQ->where('integration_type', 'ILIKE', '%' . $search . '%');
                  });
            });
        }

        // OPTIMIZED: Get paginated unmapped customers first
        $unmappedIntegrationCustomers = $query->with([
            'integration:integration_id,company_id,display_name,integration_type'
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // OPTIMIZED: Get summary stats from the paginator (no extra queries needed)
        $totalUnmapped = $unmappedIntegrationCustomers->total();
        
        // OPTIMIZED: Calculate integration type counts from paginated results only
        // This is approximate but much faster - if you need exact counts, consider caching
        $countsByIntegrationType = $unmappedIntegrationCustomers
            ->groupBy(fn($ic) => $ic->integration->integration_type ?? 'Unknown')
            ->map(fn($group) => $group->count())
            ->toArray();

        // OPTIMIZED: Load ALL active customers ONCE for the entire batch
        $existingCustomers = Customer::with('integrationCustomers:integration_customer_id,customer_id,integration_id')
            ->where('company_id', $company->company_id)
            ->where('status', 'active')
            ->get();

        // OPTIMIZED: Create service once and reuse
        $mappingService = app(CustomerMappingService::class);
        
        // OPTIMIZED: Batch process all suggestions
        $data = $unmappedIntegrationCustomers->map(function ($ic) use ($mappingService, $existingCustomers) {
            // Calculate suggestions using pre-loaded customers
            $suggestions = [];
            
            foreach ($existingCustomers as $customer) {
                $score = $mappingService->calculateSimilarity(
                    $ic->name,
                    $customer->canonical_name
                );

                if ($score > 30) {
                    $suggestions[] = [
                        'customer' => $customer,
                        'confidence_score' => $score,
                    ];
                }
            }

            // Sort by confidence score and limit to top 3
            usort($suggestions, fn($a, $b) => $b['confidence_score'] <=> $a['confidence_score']);
            $suggestions = array_slice($suggestions, 0, 3);
            
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => html_entity_decode($ic->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at ? (int)($ic->last_seen_at->timestamp * 1000) : null,
                'suggested_matches' => array_map(function($suggestion) {
                    return [
                        'customer_id' => $suggestion['customer']->customer_id,
                        'canonical_name' => $suggestion['customer']->canonical_name,
                        'is_static' => $suggestion['customer']->is_static ?? false,
                        'confidence_score' => $suggestion['confidence_score'],
                        'integrations' => $suggestion['customer']->integrationCustomers->map(function($ic) {
                            return ['id' => $ic->integration_customer_id];
                        }),
                    ];
                }, $suggestions),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'pagination' => [
                'current_page' => $unmappedIntegrationCustomers->currentPage(),
                'per_page' => $unmappedIntegrationCustomers->perPage(),
                'total' => $unmappedIntegrationCustomers->total(),
                'last_page' => $unmappedIntegrationCustomers->lastPage(),
                'from' => $unmappedIntegrationCustomers->firstItem(),
                'to' => $unmappedIntegrationCustomers->lastItem(),
            ],
            'summary' => [
                'total_unmapped' => $totalUnmapped,
                'by_integration_type' => $countsByIntegrationType,
            ],
        ]);
    }

    /**
     * Create a static customer (not linked to any integration)
     */
    public function createStaticCustomer(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $validated = $request->validate([
            'canonical_name' => 'required|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'report_destination' => 'sometimes|email|nullable',
            'metadata' => 'sometimes|array',
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::create([
                'company_id' => $company->company_id,
                'canonical_name' => $validated['canonical_name'],
                'status' => $validated['status'] ?? 'active',
                'is_static' => true,
                'report_destination' => $validated['report_destination'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Static customer created successfully.',
                'data' => [
                    'customer_id' => $customer->customer_id,
                    'canonical_name' => $customer->canonical_name,
                    'status' => $customer->status,
                    'is_static' => $customer->is_static,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create static customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function mapCustomer(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

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

        // OPTIMIZED: Eager load integration to prevent N+1
        $integrationCustomer = IntegrationCustomer::with('integration')
            ->find($request->integration_customer_id);

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
                    'is_static' => false,
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
                    'canonical_name' => $customer->canonical_name,
                    'is_static' => $customer->is_static,
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

        // OPTIMIZED: Eager load relationships to prevent N+1
        $customer = Customer::with([
            'integrationCustomers.integration',
            'integrationCustomers.customerMappings'
        ])
            ->where('company_id', $company->company_id)
            ->where('customer_id', $id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found in your company.',
            ], 404);
        }

        $integrations = $customer->integrationCustomers->map(function ($ic) {
            // Use eager-loaded relationship
            $mapping = $ic->customerMappings->first();
            
            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_id' => $ic->integration_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => e($ic->name),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at,
                'metadata' => $ic->metadata,
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

    public function getCustomerIntegrationStats(Request $request, $customerId, $integrationCustomerId)
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

        $integrationCustomer = IntegrationCustomer::where('integration_customer_id', $integrationCustomerId)
            ->with('integration')
            ->first();

        if (!$integrationCustomer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Integration customer not found.',
            ], 404);
        }

        // Get stats from integration_data table
        $integrationId = $integrationCustomer->integration_id;
        
        // Cache key for this specific customer integration stats
        $cacheKey = "customer_stats_{$customerId}_{$integrationCustomerId}";

        // Cache for 5 minutes (300 seconds)
        $stats = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($integrationCustomerId, $integrationId) {
            
            // Get all technicians from tickets
            $topTechnicians = IntegrationData::where('integration_customer_id', $integrationCustomerId)
                ->where('data_type', 'ticket')
                ->whereNotNull(\DB::raw("data_payload->>'assigned_resource'"))
                ->select(\DB::raw("data_payload->>'assigned_resource' as resource_id"), \DB::raw('COUNT(*) as ticket_count'))
                ->groupBy(\DB::raw("data_payload->>'assigned_resource'"))
                ->orderByDesc('ticket_count')
                ->limit(5) // Limit to top 5
                ->get();

            // Get resource names for these technicians
            $resourceIds = $topTechnicians->pluck('resource_id')->toArray();
            $resources = IntegrationData::where('integration_id', $integrationId)
                ->where('data_type', 'resource')
                ->whereIn(\DB::raw("CAST(data_payload->>'resource_id' AS TEXT)"), $resourceIds)
                ->get()
                ->keyBy(function($item) {
                    return $item->data_payload['resource_id'];
                });

            $technicianStats = $topTechnicians->map(function($tech) use ($resources) {
                $resource = $resources->get($tech->resource_id);
                $firstName = $resource ? ($resource->data_payload['first_name'] ?? '') : '';
                $lastName = $resource ? ($resource->data_payload['last_name'] ?? '') : '';
                $fullName = trim("$firstName $lastName") ?: "Resource #{$tech->resource_id}";
                
                return [
                    'name' => $fullName,
                    'ticket_count' => $tech->ticket_count,
                ];
            })->toArray();

            return [
                'tickets' => [
                    'total' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'ticket')
                        ->count(),
                    'open' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'ticket')
                        ->whereRaw("(data_payload->>'status')::int != ?", [5])
                        ->count(),
                    'completed' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'ticket')
                        ->whereRaw("(data_payload->>'status')::int = ?", [5])
                        ->count(),
                    'top_technicians' => $technicianStats,
                ],
                'time_entries' => [
                    'total' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'time_entry')
                        ->count(),
                    'total_hours' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'time_entry')
                        ->sum(\DB::raw("CAST(data_payload->>'hoursWorked' AS DECIMAL)")),
                    'billable_hours' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'time_entry')
                        ->whereRaw("(data_payload->>'isNonBillable')::boolean = false")
                        ->sum(\DB::raw("CAST(data_payload->>'hoursWorked' AS DECIMAL)")),
                ],
                'contracts' => [
                    'total' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'contract')
                        ->count(),
                    'active' => IntegrationData::where('integration_customer_id', $integrationCustomerId)
                        ->where('data_type', 'contract')
                        ->whereRaw("(data_payload->>'status')::int = ?", [1])
                        ->count(),
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $stats,
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
            // Count records before moving them
            $integrationCustomersMoved = IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)->count();
            $reportSchedulesMoved = $mergeCustomer->reportSchedules()->count();
            
            // Move CustomerMapping records first (before updating IntegrationCustomer)
            CustomerMapping::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);
            
            // Move IntegrationCustomer records
            IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);

            // Move ReportSchedules
            $mergeCustomer->reportSchedules()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            // Move GeneratedReports
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
                    'integration_customers_moved' => $integrationCustomersMoved,
                    'report_schedules_moved' => $reportSchedulesMoved,
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

            // FIXED: Update both customer_mappings AND integration_customers tables
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
            
            // Also update the customer_id on integration_customers table
            IntegrationCustomer::whereIn('integration_customer_id', $validated['integration_customer_ids'])
                ->update(['customer_id' => $newCustomer->customer_id]);

            
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

        // OPTIMIZED: Eager load nested relationships to prevent N+1
        $mapping = CustomerMapping::where('customer_mapping_id', $mappingId)
            ->where('customer_id', $customerId)
            ->with('integrationCustomer.integration')
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
