<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Services\ReportTemplateService;
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
            'template_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
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
        
        return response()->json([
            'success' => true,
            'data' => $template,
            'message' => 'Template created successfully',
        ], 201);
    }
    
    /**
     * Update template
     */
    public function update(Request $request, ReportTemplate $template)
    {
        $validated = $request->validate([
            'template_name' => 'string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'string',
            'frequency' => 'in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
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
}
