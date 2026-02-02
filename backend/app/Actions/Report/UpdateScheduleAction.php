<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\ReportSchedule;

final class UpdateScheduleAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function execute(ReportSchedule $schedule, array $data): ReportSchedule
    {
        if (isset($data['report_type'])) {
            $schedule->report_type = $data['report_type'];
        }

        if (isset($data['frequency'])) {
            $schedule->frequency = $data['frequency'];
        }

        if (isset($data['time_of_day'])) {
            $schedule->time_of_day = $data['time_of_day'];
        }

        if (isset($data['active'])) {
            $schedule->active = $data['active'];
        }

        if (isset($data['schedule_config'])) {
            // Merge with existing schedule_config to preserve included_sections
            $existingConfig = $schedule->schedule_config ?? [];
            $schedule->schedule_config = array_merge($existingConfig, $data['schedule_config']);
        }

        // Mark as customized if a template is linked
        if ($schedule->template_id) {
            $schedule->is_customized = true;
        }

        $schedule->save();

        return $schedule;
    }
}
