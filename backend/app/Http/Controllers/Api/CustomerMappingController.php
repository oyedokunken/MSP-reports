<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Customer\BatchAutoMatchAction;
use App\Actions\Customer\CreateStaticCustomerAction;
use App\Actions\Customer\DeleteMappingAction;
use App\Actions\Customer\GetSmartMatchSuggestionsAction;
use App\Actions\Customer\MapCustomerAction;
use App\Actions\Customer\MergeCustomersAction;
use App\Actions\Customer\SplitCustomerAction;
use App\Actions\Customer\UpdateCustomerAction;
use App\Http\Requests\Customer\BatchAutoMatchRequest;
use App\Http\Requests\Customer\CreateStaticCustomerRequest;
use App\Http\Requests\Customer\GetSmartMatchSuggestionsRequest;
use App\Http\Requests\Customer\MapCustomerRequest;
use App\Http\Requests\Customer\MergeCustomersRequest;
use App\Http\Requests\Customer\SplitCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use App\Models\IntegrationData;
use App\Services\CustomerMappingService;
use App\Services\EmbeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CustomerMappingController extends Controller
{
    public function getCustomers(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Get pagination and filter parameters
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 1 || $perPage > 100)
            $perPage = 15; // Sanity check
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
        $data = $customers->map(function (Customer $customer) {
            return [
                'customer_id' => $customer->customer_id,
                'canonical_name' => $customer->canonical_name,
                'status' => e($customer->status),
                'is_static' => $customer->is_static,
                'report_destination' => e($customer->report_destination),
                'report_schedules_count' => $customer->reportSchedules->count(),
                'integrations' => $customer->integrationCustomers->map(function ($ic) {
                    /** @var IntegrationCustomer $ic */
                    // Use eager-loaded relationship instead of query
                    /** @var CustomerMapping|null $mapping */
                    $mapping = $ic->customerMappings->first();

                    return [
                        'integration_customer_id' => $ic->integration_customer_id,
                        'integration_type' => e($ic->integration->integration_type ?? null),
                        'display_name' => e($ic->integration->display_name ?? null),
                        'external_id' => e($ic->external_id),
                        'name' => html_entity_decode($ic->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'active' => (bool) $ic->active,
                        'last_seen_at' => $ic->last_seen_at instanceof \Carbon\Carbon ? (int) $ic->last_seen_at->timestamp * 1000 : null,
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

    /**
     * Get all customer IDs for bulk selection (e.g., user scoping)
     */
    public function getAllCustomerIds(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customerIds = Customer::where('company_id', $company->company_id)
            ->pluck('customer_id')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $customerIds,
        ]);
    }

    public function getUnmappedCustomers(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Get pagination and search parameters
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 1 || $perPage > 100)
            $perPage = 15; // Sanity check
        $page = $request->input('page', 1);
        $search = $request->input('search');

        // Base query for unmapped customers
        $query = IntegrationCustomer::whereHas('integration', function ($q) use ($company) {
            $q->where('company_id', $company->company_id);
        })
            ->whereNull('customer_id');

        // Apply search filter if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', '%' . $search . '%')
                    ->orWhere('external_id', 'ILIKE', '%' . $search . '%')
                    ->orWhereHas('integration', function ($innerQ) use ($search) {
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

        // Check if we should skip suggestions (for performance, e.g. dashboard stats)
        $skipSuggestions = filter_var($request->input('skip_suggestions', false), FILTER_VALIDATE_BOOLEAN);

        if (!$skipSuggestions) {
            // OPTIMIZED: Load ALL active customers ONCE for the entire batch
            $existingCustomers = Customer::with('integrationCustomers:integration_customer_id,customer_id,integration_id')
                ->where('company_id', $company->company_id)
                ->where('status', 'active')
                ->get();

            // OPTIMIZED: Create service once and reuse
            $mappingService = app(CustomerMappingService::class);
        }

        // OPTIMIZED: Batch process all suggestions
        $data = $unmappedIntegrationCustomers->map(function ($ic) use ($skipSuggestions, $existingCustomers, $mappingService) {
            $suggestions = [];

            if (!$skipSuggestions) {
                // Calculate suggestions using pre-loaded customers
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
            }

            return [
                'integration_customer_id' => $ic->integration_customer_id,
                'integration_type' => e($ic->integration->integration_type ?? null),
                'display_name' => e($ic->integration->display_name ?? null),
                'external_id' => e($ic->external_id),
                'name' => html_entity_decode($ic->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'active' => (bool) $ic->active,
                'last_seen_at' => $ic->last_seen_at instanceof \Carbon\Carbon ? (int) $ic->last_seen_at->timestamp * 1000 : null,
                'suggested_matches' => array_map(function ($suggestion) {
                    return [
                        'customer_id' => $suggestion['customer']->customer_id,
                        'canonical_name' => $suggestion['customer']->canonical_name,
                        'is_static' => $suggestion['customer']->is_static ?? false,
                        'confidence_score' => $suggestion['confidence_score'],
                        'integrations' => $suggestion['customer']->integrationCustomers->map(function ($ic) {
                            /** @var IntegrationCustomer $ic */
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
    public function createStaticCustomer(CreateStaticCustomerRequest $request, CreateStaticCustomerAction $action): JsonResponse
    {
        $company = $request->user()->company;
        $validated = $request->validated();

        try {
            /** @var array{canonical_name: string, status?: string, report_destination?: string|null, metadata?: array<string, mixed>} $validated */
            $customer = $action->execute($company, $validated, $request->user());
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
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create static customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function mapCustomer(MapCustomerRequest $request, MapCustomerAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        try {
            /** @var array{integration_customer_id: int, customer_id?: int, create_new?: bool, canonical_name?: string, status?: string, report_destination?: string|null, metadata?: array<string, mixed>} $validated */
            $validated = $request->validated();
            $result = $action->execute($authUser, $company, $validated, $request->ip());

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => [
                    'integration_customer_id' => $request->integration_customer_id,
                    'customer_id' => $result['customer']->customer_id,
                    'canonical_name' => $result['customer']->canonical_name,
                    'is_static' => $result['customer']->is_static,
                    'created_new' => $result['created_new'],
                ],
            ]);
        } catch (ValidationException $e) {
            $statusCode = 400;
            if (str_contains($e->getMessage(), 'not found')) {
                $statusCode = 404;
            } elseif (str_contains($e->getMessage(), 'does not belong')) {
                $statusCode = 403;
            }
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to map customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerIntegrations(Request $request, int $id): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;



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
            /** @var IntegrationCustomer $ic */
            // Use eager-loaded relationship
            /** @var CustomerMapping|null $mapping */
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

    public function getCustomerIntegrationStats(Request $request, int $customerId, int $integrationCustomerId): JsonResponse
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
                ->keyBy(function ($item) {
                    /** @var IntegrationData $item */
                    return (string) ($item->data_payload['resource_id'] ?? '');
                });

            $technicianStats = $topTechnicians->map(function (IntegrationData $tech) use ($resources) {
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

    public function updateCustomer(UpdateCustomerRequest $request, int $id, UpdateCustomerAction $action): JsonResponse
    {
        $company = $request->user()->company;

        try {
            $customer = $action->execute($company, $id, $request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $customer,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function mergeCustomers(MergeCustomersRequest $request, int $id, MergeCustomersAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        try {
            $result = $action->execute(
                $authUser,
                $company,
                $id,
                $request->validated()['merge_customer_id'],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Customers merged successfully',
                'data' => $result
            ]);
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'itself') ? 400 : 404;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to merge customers: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function splitCustomer(SplitCustomerRequest $request, int $id, SplitCustomerAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;
        $validated = $request->validated();

        try {
            $result = $action->execute(
                $authUser,
                $company,
                $id,
                $validated['new_customer_name'],
                $validated['integration_customer_ids'],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Customer split successfully',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to split customer: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMapping(Request $request, int $customerId, int $mappingId, DeleteMappingAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;

        try {
            $action->execute(
                $authUser,
                $company,
                $customerId,
                $mappingId,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Mapping deleted successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete mapping: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSmartMatchSuggestions(GetSmartMatchSuggestionsRequest $request, GetSmartMatchSuggestionsAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;
        $validated = $request->validated();

        $result = $action->execute(
            $company,
            $validated['integration_customer_ids'],
            $validated['use_agent'] ?? false
        );

        return response()->json([
            'status' => 'success',
            'data' => $result['results'],
            'meta' => $result['meta'] ?? [],
            'message' => $result['message'] ?? null,
        ]);
    }

    /**
     * Check embedding service status
     */
    public function getEmbeddingStatus(Request $request): JsonResponse
    {
        $embeddingService = app(EmbeddingService::class);

        $embeddingAvailable = $embeddingService->isAvailable();
        $agentAvailable = $embeddingService->isAgentAvailable();

        return response()->json([
            'status' => 'success',
            'data' => [
                'embedding_service' => [
                    'available' => $embeddingAvailable,
                    'url' => config('services.embedding.url'),
                ],
                'agent' => [
                    'available' => $agentAvailable,
                ],
            ],
        ]);
    }

    /**
     * Batch auto-match unmapped customers using embeddings
     */
    public function batchAutoMatch(BatchAutoMatchRequest $request, BatchAutoMatchAction $action): JsonResponse
    {
        $authUser = $request->user();
        $company = $authUser->company;
        $validated = $request->validated();

        $result = $action->execute($company, $validated['integration_customer_ids'] ?? [], $validated);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
