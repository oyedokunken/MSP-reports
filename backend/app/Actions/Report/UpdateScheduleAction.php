<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Concerns\LogsAudit;
use App\Models\ReportSchedule;

final class UpdateScheduleAction
{
    use LogsAudit;

    private \App\Services\ReportScheduleService $scheduleService;

    public function __construct(\App\Services\ReportScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

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
            // But we need to be careful not to merge array keys that are meant to be replacements (like days_of_week)
            // For now, simpler merge is okay as long as frontend sends full config or partial non-conflicting config
            $existingConfig = $schedule->schedule_config ?? [];
            $schedule->schedule_config = array_merge($existingConfig, $data['schedule_config']);

            // If frequency changed, we might need to purge irrelevant config keys, but that's complex.
            // Assuming frontend sends valid config for the selected frequency.
        }

        // Mark as customized if a template is linked
        if ($schedule->template_id) {
            $schedule->is_customized = true;
        }

        // Recalculate next run if relevant fields changed or if it was inactive
        // We check isDirty because we set properties above
        if ($schedule->isDirty(['frequency', 'time_of_day', 'schedule_config']) || $schedule->next_scheduled_at === null) {
            $schedule->next_scheduled_at = $this->scheduleService->calculateNextRun($schedule);
        }

        $schedule->save();

        $this->audit($schedule->company_id, 'schedule_updated', $schedule, [
            'schedule_id' => $schedule->report_schedule_id,
            'report_type' => $schedule->report_type,
            'updated_fields' => array_keys($data),
        ]);

        return $schedule;
    }
}
