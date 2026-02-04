<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

final class DeleteReportScheduleAction
{
    /**
     * @return array{deleted_schedule_id: int, customer_name: string, report_type: string, template_deleted: bool}
     */
    public function execute(int $companyId, int $scheduleId): array
    {
        $schedule = ReportSchedule::where('report_schedule_id', $scheduleId)
            ->where('company_id', $companyId)
            ->with('customer:customer_id,canonical_name')
            ->first();

        if (!$schedule) {
            throw ValidationException::withMessages([
                'schedule_id' => ['Schedule not found or access denied.'],
            ]);
        }

        $customerName = $schedule->customer->canonical_name ?? 'Unknown';
        $reportType = $schedule->report_type;
        $templateId = $schedule->template_id;

        // Delete the schedule
        $schedule->delete();

        // If schedule has a template_id, also delete the template
        $templateDeleted = false;
        if ($templateId) {
            try {
                $template = ReportTemplate::where('template_id', $templateId)
                    ->where('company_id', $companyId)
                    ->first();
                
                if ($template) {
                    $template->delete();
                    $templateDeleted = true;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete associated template', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'deleted_schedule_id' => $scheduleId,
            'customer_name' => $customerName,
            'report_type' => $reportType,
            'template_deleted' => $templateDeleted,
        ];
    }
}
