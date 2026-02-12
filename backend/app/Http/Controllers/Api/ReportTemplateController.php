<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Concerns\LogsAudit;
use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Services\ReportTemplateService;
use App\Services\ReportRegistry;
use App\Http\Requests\ReportTemplate\StoreReportTemplateRequest;
use App\Http\Requests\ReportTemplate\UpdateReportTemplateRequest;
use App\Http\Requests\ReportTemplate\ApplyTemplateRequest;
use App\Http\Requests\ReportTemplate\ApplyToCustomersRequest;
use App\Http\Requests\ReportTemplate\GetAvailableReportTypesRequest;
use App\Http\Requests\ReportTemplate\GetReportTemplatesRequest;
use App\Services\InAppNotificationService;
use Illuminate\Http\Request;

final class ReportTemplateController extends Controller
{
    use LogsAudit;

    public function __construct(
        private ReportTemplateService $templateService,
        private InAppNotificationService $notificationService,
    ) {}
    
    /**
     * List all templates with schedule counts
     * 
     * Query params:
     * - report_type: Filter by report type
     * - active: Filter by active status (true/false)
     * - global: Filter for global templates only (true) or customer-specific only (false)
     */
    public function index(GetReportTemplatesRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $templates = ReportTemplate::query()
            ->with('customer')
            ->withCount('schedules')
            ->when($validated['report_type'] ?? null, fn($q, $type) => $q->where('report_type', $type))
            ->when(isset($validated['active']), fn($q) => $q->where('active', $request->boolean('active')))
            ->when(isset($validated['global']), function($q) use ($request) {
                if ($request->boolean('global')) {
                    return $q->whereNull('customer_id');
                } else {
                    return $q->whereNotNull('customer_id');
                }
            })
            ->orderByRaw('customer_id IS NOT NULL, template_name') // Global templates first
            ->get()
            ->map(function ($template) {
                $template->is_global = $template->customer_id === null;
                return $template;
            });
            
        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }
    
    /**
     * Get single template
     */
    public function show(ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }
    
    /**
     * Create template
     * 
     * If customer_id is provided, creates a customer-specific template and auto-creates a schedule.
     * If customer_id is null, creates a global template that can be applied to any customer later.
     */
    public function store(StoreReportTemplateRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        
        $template = ReportTemplate::create([
            'company_id' => auth()->user()->company_id,
            ...$validated,
        ]);
        
        $schedule = null;
        
        // Only auto-create schedule if customer_id is provided (customer-specific template)
        if (!empty($validated['customer_id'])) {
            // Merge included_sections into schedule_config for proper display
            $scheduleConfig = $validated['schedule_config'] ?? [];
            if (!empty($validated['included_sections'])) {
                $scheduleConfig['included_sections'] = $validated['included_sections'];
            }
            if (!empty($validated['report_options'])) {
                $scheduleConfig['report_options'] = $validated['report_options'];
            }
            
            $schedule = \App\Models\ReportSchedule::create([
                'company_id' => auth()->user()->company_id,
                'customer_id' => $validated['customer_id'],
                'template_id' => $template->template_id,
                'report_type' => $validated['report_type'],
                'frequency' => $validated['frequency'],
                'schedule_config' => !empty($scheduleConfig) ? $scheduleConfig : null,
                'time_of_day' => $validated['time_of_day'] ?? '09:00',
                'timezone' => $validated['timezone'] ?? 'Australia/Brisbane',
                'active' => $validated['active'] ?? true,
            ]);
        }
        
        $isGlobal = empty($validated['customer_id']);
        $message = $isGlobal 
            ? 'Global template created successfully. Apply it to customers to create schedules.'
            : 'Template created and schedule activated for customer';
        
        // Send notification
        $this->notificationService->templateCreated($template, auth()->user());

        $this->audit(auth()->user()->company_id, 'template_created', $template, [
            'template_name' => $template->template_name,
            'report_type' => $template->report_type,
            'is_global' => $isGlobal,
        ]);

        return response()->json([
            'success' => true,
            'data' => $template->load('customer'),
            'schedule' => $schedule,
            'is_global' => $isGlobal,
            'message' => $message,
        ], 201);
    }
    
    /**
     * Update template
     */
    public function update(UpdateReportTemplateRequest $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        \Log::info('Template update request', [
            'template_id' => $template->template_id,
            'request_data' => $request->all(),
        ]);

        $validated = $request->validated();
        
        $template->update($validated);
        
        // Sync schedules if this is a global template or belongs to this customer
        $syncStats = $this->templateService->syncLinkedSchedules($template);

        $this->audit(auth()->user()->company_id, 'template_updated', $template, [
            'template_name' => $template->template_name,
            'updated_fields' => array_keys($validated),
        ]);

        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template updated successfully',
            'sync_stats' => $syncStats,
        ]);
    }
    
    /**
     * Delete template and cascade delete associated schedules
     */
    public function destroy(Request $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        \Log::info('Delete template endpoint called', [
            'template_id' => $template->template_id,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
        ]);
        
        $schedulesCount = $template->schedules()->count();
        
        \Log::info('Template found with schedules', [
            'template_id' => $template->template_id,
            'template_name' => $template->template_name,
            'company_id' => $template->company_id,
            'schedules_count' => $schedulesCount,
        ]);
        
        $templateName = $template->template_name;
        $templateType = $template->report_type;
        $companyId = $template->company_id;

        // Delete template (cascade will handle schedules via foreign key)
        $deleted = $template->delete();

        $this->audit($companyId, 'template_deleted', null, [
            'template_name' => $templateName,
            'report_type' => $templateType,
            'schedules_deleted' => $schedulesCount,
        ]);
        
        \Log::info('Delete result', [
            'deleted' => $deleted,
            'template_id' => $template->template_id,
            'schedules_deleted' => $schedulesCount,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
            'schedules_deleted' => $schedulesCount,
        ]);
    }
    
    /**
     * Apply template to single customer
     */
    public function applyToCustomer(ApplyTemplateRequest $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        
        /** @var \App\Models\Customer $customer */
        $customer = Customer::findOrFail($validated['customer_id']);
        $schedule = $this->templateService->applyTemplateToCustomer($template, $customer);

        $this->audit(auth()->user()->company_id, 'template_applied', $template, [
            'template_name' => $template->template_name,
            'customer_name' => $customer->canonical_name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $schedule,
            'message' => 'Template applied successfully',
        ], 201);
    }
    
    /**
     * Apply template to multiple customers
     */
    public function applyToCustomers(ApplyToCustomersRequest $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        
        $schedules = $this->templateService->applyTemplateToCustomers(
            $template,
            $validated['customer_ids']
        );

        $this->audit(auth()->user()->company_id, 'template_bulk_applied', $template, [
            'template_name' => $template->template_name,
            'customer_count' => count($validated['customer_ids']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $schedules,
            'message' => 'Template applied to ' . count($schedules) . ' customers',
        ], 201);
    }

    /**
     * Generate preview report from template
     * 
     * For global templates, requires customer_id in request to preview with a specific customer.
     */
    public function preview(Request $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        try {
            // Load customer relationship
            $template->load('customer');
            
            // For global templates, allow specifying a customer for preview
            if (!$template->customer && $request->has('customer_id')) {
                $customer = Customer::find($request->customer_id);
                if ($customer) {
                    $template->setRelation('customer', $customer);
                }
            }
            
            if (!$template->customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template has no associated customer. For global templates, provide customer_id parameter.',
                ], 400);
            }

            // Generate a one-time preview report using the template configuration
            $report = $this->templateService->generatePreviewReport($template);
            
            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Preview report generated successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Template preview generation failed', [
                'template_id' => $template->template_id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available report types for a specific customer
     */
    public function getAvailableReportTypes(GetAvailableReportTypesRequest $request, ReportRegistry $reportRegistry): \Illuminate\Http\JsonResponse
    {
        \Log::info('getAvailableReportTypes called', [
            'all_input' => $request->all(),
            'query' => $request->query(),
            'customer_id' => $request->input('customer_id'),
        ]);

        $validated = $request->validated();

        $customerId = (int) $validated['customer_id'];
        $customer = Customer::with('integrationCustomers.integration')->find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $customerIntegrations = $customer->integrationCustomers
            ->map(function ($ic) {
                /** @var \App\Models\IntegrationCustomer $ic */
                return strtolower($ic->integration->integration_type);
            })
            ->unique()
            ->values()
            ->toArray();

        $allReports = $reportRegistry->getAvailable($customer->company_id);
        
        $reportTypes = [];
        foreach ($allReports as $report) {
            $reportInstance = $reportRegistry->get($report['id'], $customer->company_id);
            if (!$reportInstance) {
                continue;
            }
            
            $metadata = $reportInstance->getMetadata();
            
            $requiredIntegrations = $report['required_integrations'] ?? [];
            
            if (!empty($requiredIntegrations)) {
                $hasAllRequired = true;
                foreach ($requiredIntegrations as $required) {
                    if (strtolower($required) === 'multiple') {
                        if (count($customerIntegrations) < 2) {
                            $hasAllRequired = false;
                            break;
                        }
                        continue;
                    }

                    $hasIntegration = in_array(strtolower($required), $customerIntegrations);
                    if (!$hasIntegration) {
                        $hasAllRequired = false;
                        break;
                    }
                }
                
                if (!$hasAllRequired) {
                    continue;
                }
            }
            
            // Check data availability
            $hasData = null;
            $reportClass = $report['class'] ?? get_class($reportInstance);
            
            if ($reportClass && method_exists($reportClass, 'hasRecentData')) {
                try {
                    // Check last 30 days to see if integration is active/has data
                    $hasData = $reportClass::hasRecentData($customerId);
                } catch (\Exception $e) {
                    \Log::warning("Failed to check data availability for template selector {$report['id']}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $reportTypes[] = [
                'type' => $report['id'],
                'name' => $report['name'],
                'description' => $report['description'],
                'required_integrations' => $requiredIntegrations,
                'has_data' => $hasData,
                'available_sections' => $report['available_sections'] ?? [
                    ['id' => 'all', 'label' => 'All Sections', 'default' => true],
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'customer_id' => $customerId,
                'report_types' => $reportTypes,
            ],
        ]);
    }

    /**
     * Get all available report types (for global templates - no customer filtering)
     */
    public function getAllReportTypes(ReportRegistry $reportRegistry): \Illuminate\Http\JsonResponse
    {
        $companyId = auth()->user()->company_id;
        $allReports = $reportRegistry->getAvailable($companyId);
        
        $reportTypes = [];
        foreach ($allReports as $report) {
            $reportInstance = $reportRegistry->get($report['id'], $companyId);
            if (!$reportInstance) {
                continue;
            }
            
            $metadata = $reportInstance->getMetadata();
            
            $reportTypes[] = [
                'type' => $report['id'],
                'name' => $report['name'],
                'description' => $report['description'],
                'required_integrations' => $report['required_integrations'] ?? [],
                'available_sections' => $report['available_sections'] ?? [
                    ['id' => 'all', 'label' => 'All Sections', 'default' => true],
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'report_types' => $reportTypes,
            ],
        ]);
    }
    
    /**
     * Get customers who have this template applied via their schedules
     */
    public function getAppliedCustomers(ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        $customers = Customer::query()
            ->whereIn('customer_id', function($query) use ($template) {
                $query->select('customer_id')
                    ->from('report_schedules')
                    ->where('template_id', $template->template_id);
            })
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }
}
