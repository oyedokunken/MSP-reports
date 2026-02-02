<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Models\ReportSchedule;
use Illuminate\Foundation\Http\FormRequest;

class ResetScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $scheduleId = $this->route('schedule_id');

        return ReportSchedule::where('company_id', $user->company_id)
            ->where('report_schedule_id', $scheduleId)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
