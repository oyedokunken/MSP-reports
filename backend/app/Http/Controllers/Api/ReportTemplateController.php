<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Services\ReportTemplateService;
use App\Services\ReportRegistry;
use Illuminate\Http\Request;

final class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templateService
    ) {}
    
    /**
     * List all templates with schedule counts
     * 
     * Query params:
     * - report_type: Filter by report type
     * - active: Filter by active status (true/false)
     * - global: Filter for global templates only (true) or customer-specific only (false)
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $templates = ReportTemplate::query()
            ->with('customer')
            ->withCount('schedules')
            ->when($request->report_type, fn($q, $type) => $q->where('report_type', $type))
            ->when($request->active !== null, fn($q) => $q->where('active', $request->boolean('active')))
            ->when($request->has('global'), function($q) use ($request) {
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
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:customers,customer_id',
            'template_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|string|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
        ]);
        
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
    public function update(Request $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        \Log::info('Template update request', [
            'template_id' => $template->template_id,
            'request_data' => $request->all(),
        ]);

        $validated = $request->validate([
            'template_name' => 'string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'string',
            'frequency' => 'in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|string|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
            'customer_id' => 'sometimes|integer|exists:customers,customer_id',
        ]);
        
        $template->update($validated);
        
        // Sync schedules if this is a global template or belongs to this customer
        $syncStats = $this->templateService->syncLinkedSchedules($template);
        
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
        
        // Delete template (cascade will handle schedules via foreign key)
        $deleted = $template->delete();
        
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
    public function applyToCustomer(Request $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);
        
        /** @var \App\Models\Customer $customer */
        $customer = Customer::findOrFail($validated['customer_id']);
        $schedule = $this->templateService->applyTemplateToCustomer($template, $customer);
        
        return response()->json([
            'success' => true,
            'data' => $schedule,
            'message' => 'Template applied successfully',
        ], 201);
    }
    
    /**
     * Apply template to multiple customers
     */
    public function applyToCustomers(Request $request, ReportTemplate $template): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:customers,customer_id',
        ]);
        
        $schedules = $this->templateService->applyTemplateToCustomers(
            $template,
            $validated['customer_ids']
        );
        
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
    public function getAvailableReportTypes(Request $request, ReportRegistry $reportRegistry): \Illuminate\Http\JsonResponse
    {
        \Log::info('getAvailableReportTypes called', [
            'all_input' => $request->all(),
            'query' => $request->query(),
            'customer_id' => $request->input('customer_id'),
        ]);

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,customer_id',
        ]);

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
