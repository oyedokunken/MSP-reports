<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Services\ReportTemplateService;
use App\Services\ReportRegistry;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templateService
    ) {}
    
    /**
     * List all templates
     */
    public function index(Request $request)
    {
        $templates = ReportTemplate::query()
            ->with('customer')
            ->when($request->report_type, fn($q, $type) => $q->where('report_type', $type))
            ->when($request->active !== null, fn($q) => $q->where('active', $request->boolean('active')))
            ->orderBy('template_name')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }
    
    /**
     * Get single template
     */
    public function show(ReportTemplate $template)
    {
        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }
    
    /**
     * Create template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,customer_id',
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
        
        // Auto-create ReportSchedule for the customer
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
            'report_template_id' => $template->template_id,
            'report_type' => $validated['report_type'],
            'frequency' => $validated['frequency'],
            'schedule_config' => !empty($scheduleConfig) ? $scheduleConfig : null,
            'time_of_day' => $validated['time_of_day'] ?? '09:00',
            'timezone' => $validated['timezone'] ?? 'Australia/Brisbane',
            'active' => $validated['active'] ?? true,
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $template->load('customer'),
            'schedule' => $schedule,
            'message' => 'Template created and schedule activated for customer',
        ], 201);
    }
    
    /**
     * Update template
     */
    public function update(Request $request, ReportTemplate $template)
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
        
        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template updated successfully',
        ]);
    }
    
    /**
     * Delete template
     */
    public function destroy(Request $request, $report_template)
    {
        \Log::info('Delete template endpoint called', [
            'report_template_param' => $report_template,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
        ]);
        
        // Manually find the template
        $template = ReportTemplate::where('template_id', $report_template)->firstOrFail();
        
        \Log::info('Template found', [
            'template_id' => $template->template_id,
            'template_name' => $template->template_name,
            'company_id' => $template->company_id,
        ]);
        
        $deleted = $template->delete();
        
        \Log::info('Delete result', [
            'deleted' => $deleted,
            'template_id' => $template->template_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }
    
    /**
     * Apply template to single customer
     */
    public function applyToCustomer(Request $request, ReportTemplate $template)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
        ]);
        
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
    public function applyToCustomers(Request $request, ReportTemplate $template)
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
     */
    public function preview(ReportTemplate $template)
    {
        try {
            // Load customer relationship
            $template->load('customer');
            
            if (!$template->customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template has no associated customer',
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
    public function getAvailableReportTypes(Request $request, ReportRegistry $reportRegistry)
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
            ->map(fn($ic) => strtolower($ic->integration->integration_type))
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
            
            $reportTypes[] = [
                'type' => $report['id'],
                'name' => $report['name'] ?? $metadata['name'] ?? 'Unknown Report',
                'description' => $report['description'] ?? $metadata['description'] ?? '',
                'required_integrations' => $requiredIntegrations,
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
}
