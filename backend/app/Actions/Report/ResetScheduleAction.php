<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\ReportSchedule;
use Illuminate\Validation\ValidationException;

final class ResetScheduleAction
{
    public function execute(ReportSchedule $schedule): ReportSchedule
    {
        if (!$schedule->template_id || !$schedule->template) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This schedule is not linked to a template.'
            ]);
        }

        $template = $schedule->template;

        // Revert to template settings
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
            'is_customized' => false,
        ]);

        // Also revert customer report_configuration to template versions if applicable
        $customer = $schedule->customer;
        if ($template->included_sections || $template->delivery_settings || $template->report_options) {
            $reportConfig = $customer->report_configuration ?? [];
            
            if ($template->included_sections) {
                $reportConfig['included_sections'] = $template->included_sections;
            }
            if ($template->delivery_settings) {
                $reportConfig['delivery_settings'] = $template->delivery_settings;
            }
            if ($template->report_options) {
                $reportConfig['report_options'] = $template->report_options;
            }

            $customer->update(['report_configuration' => $reportConfig]);
        }

        return $schedule;
    }
}
