<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Concerns\LogsAudit;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BulkGenerateReportsAction
{
    use LogsAudit;

    private GenerateReportAction $generateReportAction;

    public function __construct(GenerateReportAction $generateReportAction)
    {
        $this->generateReportAction = $generateReportAction;
    }

    /**
     * @param array<int> $customerIds
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function execute(Company $company, array $customerIds, array $options = []): array
    {
        $reportType = $options['report_type'] ?? null;
        $frequencyOverride = $options['frequency'] ?? null;
        $previewOnly = $options['preview_only'] ?? false;
        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;

        // Verify all customers belong to this company
        $customers = Customer::where('company_id', $company->company_id)
            ->whereIn('customer_id', $customerIds)
            ->get();

        if ($customers->count() !== count($customerIds)) {
            $foundIds = $customers->pluck('customer_id')->toArray();
            $missingIds = array_diff($customerIds, $foundIds);
            throw ValidationException::withMessages([
                'customer_ids' => 'Some customers not found or not accessible: ' . implode(', ', $missingIds)
            ]);
        }

        $results = [
            'total_customers' => count($customerIds),
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'reports' => [],
            'errors' => [],
        ];

        foreach ($customers as $customer) {
            try {
                // Get active schedules for this customer
                $schedulesQuery = ReportSchedule::where('customer_id', $customer->customer_id)
                    ->where('company_id', $company->company_id)
                    ->where('active', true);

                if ($reportType) {
                    $schedulesQuery->where('report_type', $reportType);
                }

                $schedules = $schedulesQuery->get();

                if ($schedules->isEmpty()) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'customer_id' => $customer->customer_id,
                        'customer_name' => $customer->canonical_name,
                        'error' => 'No active report schedules found'
                    ];
                    continue;
                }

                $customerReports = [];

                foreach ($schedules as $schedule) {
                    try {
                        $generatedReport = $this->generateReportAction->execute($schedule, [
                            'frequency' => $frequencyOverride,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'preview_only' => $previewOnly,
                        ]);

                        $reportName = $schedule->template 
                            ? $schedule->template->template_name 
                            : ucfirst(str_replace('_', ' ', (string) $schedule->report_type));

                        $customerReports[] = [
                            'generated_report_id' => $generatedReport->generated_report_id,
                            'report_type' => $schedule->report_type,
                            'report_name' => $reportName,
                            'status' => $previewOnly ? 'preview' : 'queued',
                        ];
                    } catch (\Exception $e) {
                        $results['errors'][] = [
                            'customer_id' => $customer->customer_id,
                            'customer_name' => $customer->canonical_name,
                            'report_type' => $schedule->report_type,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                if (!empty($customerReports)) {
                    $results['successful']++;
                    $results['reports'][] = [
                        'customer_id' => $customer->customer_id,
                        'customer_name' => $customer->canonical_name,
                        'reports_generated' => $customerReports,
                    ];
                } else {
                    $results['failed']++;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'customer_id' => $customer->customer_id,
                    'customer_name' => $customer->canonical_name,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->audit($company->company_id, 'report_bulk_generated', null, [
            'total_customers' => $results['total_customers'],
            'successful' => $results['successful'],
            'failed' => $results['failed'],
            'skipped' => $results['skipped'],
            'report_type' => $reportType,
        ]);

        return $results;
    }
}
