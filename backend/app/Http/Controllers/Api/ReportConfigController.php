<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use Carbon\Carbon;

class ReportConfigController extends Controller
{
    public function getAvailableReports(Request $request)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        // Scan the Reports directory for available report types
        $reportsPath = app_path('Reports');
        $availableReports = [];

        if (is_dir($reportsPath)) {
            $directories = array_diff(scandir($reportsPath), ['.', '..', 'Contracts']);
            
            foreach ($directories as $reportDir) {
                $configPath = $reportsPath . '/' . $reportDir . '/config.php';
                
                if (file_exists($configPath)) {
                    $config = include $configPath;
                    
                    // Add the report type identifier
                    $reportType = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $reportDir));
                    
                    $availableReports[] = [
                        'report_type' => $reportType,
                        'name' => $config['name'] ?? ucwords(str_replace('_', ' ', $reportType)),
                        'description' => $config['description'] ?? '',
                        'frequency_options' => $config['supported_frequencies'] ?? ['on-demand'],
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $availableReports
        ]);
    }

    public function getCustomerReports(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        }

        // Get all report schedules for this customer
        $schedules = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->get()
            ->map(function ($schedule) {
                return [
                    'report_schedule_id' => $schedule->report_schedule_id,
                    'report_type' => $schedule->report_type,
                    'frequency' => $schedule->frequency,
                    'time_of_day' => $schedule->time_of_day,
                    'active' => $schedule->active,
                    'next_scheduled_at' => $schedule->next_scheduled_at,
                    'last_generated_at' => $schedule->last_generated_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer_id' => $customer->customer_id,
                'customer_name' => $customer->canonical_name,
                'report_schedules' => $schedules
            ]
        ]);
    }

    public function updateCustomerReports(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json(['status'=>'error','message'=>'Customer not found'], 404);
        }

        $validated = $request->validate([
            'schedules' => 'required|array|min:1',
            'schedules.*.report_type' => 'required|string',
            'schedules.*.frequency' => 'required|string|in:daily,weekly,monthly,yearly,on-demand',
            'schedules.*.time_of_day' => 'nullable|string',
            'schedules.*.active' => 'nullable|boolean',
            'schedules.*.schedule_config' => 'nullable|array',
        ]);

        $schedulesCreated = 0;
        $schedulesUpdated = 0;

        foreach ($validated['schedules'] as $scheduleData) {
            // Check if schedule already exists for this report type
            $existingSchedule = ReportSchedule::where('customer_id', $customer->customer_id)
                ->where('company_id', $company->company_id)
                ->where('report_type', $scheduleData['report_type'])
                ->first();

            if ($existingSchedule) {
                // Update existing schedule
                $existingSchedule->update([
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                ]);
                $schedulesUpdated++;
            } else {
                // Create new schedule
                ReportSchedule::create([
                    'company_id' => $company->company_id,
                    'customer_id' => $customer->customer_id,
                    'report_type' => $scheduleData['report_type'],
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? '09:00',
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'timezone' => $company->settings['reports']['report_timezone'] ?? 'UTC',
                ]);
                $schedulesCreated++;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report schedules updated successfully',
            'data' => [
                'schedules_created' => $schedulesCreated,
                'schedules_updated' => $schedulesUpdated
            ]
        ]);
    }

    public function updateCustomerReportDestination(Request $request, $id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $id)
            ->where('company_id', $company->company_id)
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        }

        $validated = $request->validate([
            'method' => 'required|string|in:email,api,download',
            'destination' => 'required|string|email',
            'cc' => 'nullable|array',
            'cc.*' => 'nullable|email'
        ]);

        $reportConfig = $customer->report_configuration ?? [];
        $reportConfig['delivery'] = $validated;

        $customer->report_configuration = $reportConfig;
        $customer->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Customer report delivery destination updated',
            'data' => $customer->report_configuration['delivery']
        ]);
    }

    public function generateReport(Request $request, $customer_id)
    {
        $authUser = $request->user();
        $company = $authUser->company;

        $customer = Customer::where('customer_id', $customer_id)
            ->where('company_id', $company->company_id)
            ->firstOrFail();

        // Get active report schedules for this customer
        $schedules = ReportSchedule::where('customer_id', $customer->customer_id)
            ->where('company_id', $company->company_id)
            ->where('active', true)
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active report schedules for this customer. Please create schedules first.'
            ], 400);
        }

        $generatedReports = [];
        $errors = [];

        foreach ($schedules as $schedule) {
            try {
                // Convert report_type to class name
                $reportType = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $schedule->report_type)));
                $class = "App\\Reports\\{$reportType}\\{$reportType}";

                if (!class_exists($class)) {
                    $errors[] = "Report class not found: {$class}";
                    continue;
                }

                $reportInstance = new $class();

                // Validate data availability
                if (!method_exists($reportInstance, 'validate') || !$reportInstance->validate($customer)) {
                    $errors[] = "Insufficient data for {$schedule->report_type}";
                    continue;
                }

                // Generate the report
                $period = $schedule->frequency === 'monthly' ? 'monthly' : 'weekly';
                $filePath = $reportInstance->generate($customer, $period);
                
                // Deliver the report
                if (method_exists($reportInstance, 'deliver')) {
                    $reportInstance->deliver($company, $customer, $filePath);
                }

                // Record the generated report
                $generatedReports[] = GeneratedReport::create([
                    'company_id' => $company->company_id,
                    'customer_id' => $customer->customer_id,
                    'report_schedule_id' => $schedule->report_schedule_id,
                    'generated_at' => now(),
                    'report_period' => $period,
                    'status' => 'completed',
                    'file_path' => str_replace(storage_path('app/'), '', $filePath),
                ]);

                // Update schedule's last generated timestamp
                $schedule->update(['last_generated_at' => now()]);

            } catch (\Exception $e) {
                $errors[] = "Error generating {$schedule->report_type}: " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report generation completed',
            'data' => [
                'generated' => count($generatedReports),
                'errors' => $errors,
                'reports' => $generatedReports
            ]
        ]);
    }
}
