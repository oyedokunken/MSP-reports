<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'report_type' => 'nullable|string',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly,on-demand',
            'time_of_day' => 'nullable|string',
            'active' => 'nullable|boolean',
            'schedule_config' => 'nullable|array',
        ];
    }
}
