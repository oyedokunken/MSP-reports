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

        $companyReportSettings = $company->settings['reports'] ?? [];

        $data = [
            'default_destination' => $companyReportSettings['default_report_destination'] ?? null,
            'timezone' => $companyReportSettings['report_timezone'] ?? null,
            'retention_days' => $companyReportSettings['report_retention_days'] ?? null,
            'format' => $companyReportSettings['report_format'] ?? [],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data
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

        $companyReports = $company->settings['reports'] ?? [];

        $customerReports = $customer->report_configuration ?? [];

        $effectiveConfig = [
            'enabled_reports' => $customerReports['enabled_reports'] ?? [],
            'report_branding' => $customerReports['report_branding'] ?? [],
            'delivery' => $customerReports['delivery'] ?? [
                'method' => 'email',
                'destination' => $companyReports['default_report_destination'] ?? null,
                'cc' => []
            ],
            'timezone' => $companyReports['report_timezone'] ?? null,
            'retention_days' => $companyReports['report_retention_days'] ?? null,
            'format' => $companyReports['report_format'] ?? [],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $effectiveConfig
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
            'enabled_reports' => 'required|array',
            'enabled_reports.*.report_type' => 'required|string',
            'report_branding' => 'nullable|array',
            'delivery' => 'nullable|array',
            'delivery.method' => 'nullable|string|in:email,api,download',
            'delivery.destination' => 'nullable|string|email',
            'delivery.cc' => 'nullable|array',
            'delivery.cc.*' => 'nullable|email'
        ]);

        $enabledReports = [];

        foreach ($validated['enabled_reports'] as $report) {
            if (isset($report['schedule_id']) && $report['schedule_id']) {
                $schedule = ReportSchedule::find($report['schedule_id']);
            } else {
                $schedule = ReportSchedule::create([
                    'company_id' => $company->company_id,
                    'customer_id' => $customer->customer_id,
                    'report_type' => $report['report_type'],
                    'frequency' => 'on-demand',
                    'schedule_config' => null,
                    'time_of_day' => null,
                    'active' => true,
                    'timezone' => $company->settings['reports']['report_timezone'] ?? 'UTC',
                ]);
            }

            $enabledReports[] = [
                'report_type' => $report['report_type'],
                'schedule_id' => $schedule->report_schedule_id
            ];
        }

        $customer->report_configuration = array_merge(
            $customer->report_configuration ?? [],
            [
                'enabled_reports' => $enabledReports,
                'report_branding' => $validated['report_branding'] ?? [],
                'delivery' => $validated['delivery'] ?? ($customer->report_configuration['delivery'] ?? [])
            ]
        );

        $customer->save();

        return response()->json([
            'status'=>'success',
            'message'=>'Customer report configuration updated',
            'data'=>$customer->report_configuration
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
            ->first();

        if (!$customer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        }

        $reportConfig = $customer->report_configuration ?? [];
        $enabledReports = $reportConfig['enabled_reports'] ?? [];

        if (empty($enabledReports)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No enabled reports for this customer'
            ], 400);
        }

        $generatedReports = [];

        foreach ($enabledReports as $report) {
            // Create sample file
            $timestamp = now()->format('Ymd_His');
            $filename = "report_{$customer->canonical_name}_{$timestamp}.txt";
            $filePath = "reports/{$filename}";

            $content = "Sample Report\nCompany: {$company->name}\nCustomer: {$customer->canonical_name}\nGenerated: {$timestamp}";
            \Storage::put($filePath, $content);

            // Create record
            $generatedReport = \App\Models\GeneratedReport::create([
                'company_id' => $company->company_id,
                'customer_id' => $customer->customer_id,
                'report_schedule_id' => $report['schedule_id'] ?? null,
                'generated_at' => now(),
                'report_period' => null,
                'status' => 'completed', // ✅ mark as done
                'delivery_method' => $reportConfig['delivery']['method'] ?? 'email',
                'file_path' => $filePath,
                'file_size' => strlen($content),
                'delivered' => false,
                'delivered_at' => null,
                'error_message' => null,
                'retention_until' => now()->addDays($company->settings['reports']['report_retention_days'] ?? 1095)
            ]);

            $generatedReports[] = $generatedReport;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Report generated successfully',
            'data' => $generatedReports
        ]);
    }
}
