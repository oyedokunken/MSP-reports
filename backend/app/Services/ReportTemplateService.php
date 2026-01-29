<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ReportTemplateService
{
    /**
     * Apply template to a single customer (COPY settings, not link)
     */
    public function applyTemplateToCustomer(ReportTemplate $template, Customer $customer): ReportSchedule
    {
        // Create a new schedule by COPYING template values
        $schedule = ReportSchedule::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->customer_id,
            'template_id' => $template->template_id,  // Link to template for cascade delete
            'report_type' => $template->report_type,
            'frequency' => $template->frequency,
            'schedule_config' => $template->schedule_config,  // COPY, not reference
            'time_of_day' => $template->time_of_day,
            'timezone' => $template->timezone ?? 'Australia/Brisbane',
            'active' => true,
        ]);

        // Store additional settings in customer's report_configuration
        if ($template->included_sections || $template->delivery_settings || $template->report_options) {
            $customer->update([
                'report_configuration' => array_merge(
                    $customer->report_configuration ?? [],
                    [
                        'included_sections' => $template->included_sections,
                        'delivery_settings' => $template->delivery_settings,
                        'report_options' => $template->report_options,
                    ]
                ),
            ]);
        }

        return $schedule->fresh();
    }

    /**
     * Apply template to multiple customers
     * 
     * @param ReportTemplate $template
     * @param array<int, int> $customerIds
     * @return Collection<int, ReportSchedule>
     */
    public function applyTemplateToCustomers(ReportTemplate $template, array $customerIds): Collection
    {
        $schedules = collect();

        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $schedules->push($this->applyTemplateToCustomer($template, $customer));
            }
        }

        return $schedules;
    }

    /**
     * Generate a preview report from template with real customer data
     *
     * This creates a temporary schedule and uses the existing report generation system.
     */
    public function generatePreviewReport(ReportTemplate $template): GeneratedReport
    {
        // Prepare schedule config with template's section and report options
        $scheduleConfig = array_merge(
            $template->schedule_config ?? [],
            [
                'included_sections' => $template->included_sections,
                'report_options' => $template->report_options,
            ]
        );

        // Create a temporary schedule for the preview
        $tempSchedule = ReportSchedule::create([
            'company_id' => $template->company_id,
            'customer_id' => $template->customer_id,
            'template_id' => $template->template_id,
            'report_type' => $template->report_type,
            'frequency' => 'on-demand',
            'schedule_config' => $scheduleConfig,
            'time_of_day' => '00:00',
            'timezone' => $template->timezone ?? 'Australia/Brisbane',
            'active' => false,  // Inactive so it won't be picked up by scheduler
        ]);

        try {
            // Use the existing report generation service
            $reportService = app(\App\Services\ReportGenerationService::class);

            // Generate the report for last month
            $startDate = Carbon::now()->subMonth()->startOfMonth();
            $endDate = Carbon::now()->subMonth()->endOfMonth();

            $report = $reportService->generateReport($tempSchedule, $startDate, $endDate);

            // Delete the temporary schedule
            $tempSchedule->delete();

            return $report;
        } catch (\Exception $e) {
            // Clean up temporary schedule on error
            $tempSchedule->delete();
            throw $e;
        }
    }

    /**
     * Sync template changes to all linked schedules that haven't been customized.
     * 
     * @param ReportTemplate $template
     * @return array{updated: int, skipped: int}
     */
    public function syncLinkedSchedules(ReportTemplate $template): array
    {
        $schedules = ReportSchedule::where('template_id', $template->template_id)
            ->where('is_customized', false)
            ->get();

        $updatedCount = 0;
        $skippedCount = ReportSchedule::where('template_id', $template->template_id)
            ->where('is_customized', true)
            ->count();

        foreach ($schedules as $schedule) {
            $scheduleConfig = $template->schedule_config ?? [];
            if ($template->included_sections) {
                $scheduleConfig['included_sections'] = $template->included_sections;
            }
            if ($template->report_options) {
                $scheduleConfig['report_options'] = $template->report_options;
            }

            $schedule->update([
                'report_type' => $template->report_type,
                'frequency' => $template->frequency,
                'schedule_config' => !empty($scheduleConfig) ? $scheduleConfig : null,
                'time_of_day' => $template->time_of_day,
                'timezone' => $template->timezone,
            ]);

            // Also update customer config if needed
            $customer = $schedule->customer;
            if ($template->included_sections || $template->delivery_settings || $template->report_options) {
                $customer->update([
                    'report_configuration' => array_merge(
                        $customer->report_configuration ?? [],
                        [
                            'included_sections' => $template->included_sections,
                            'delivery_settings' => $template->delivery_settings,
                            'report_options' => $template->report_options,
                        ]
                    ),
                ]);
            }

            $updatedCount++;
        }

        return [
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
        ];
    }
}
