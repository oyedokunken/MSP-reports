<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_name' => 'string|max:255',
            'description' => 'nullable|string',
            'report_type' => 'string',
            'frequency' => 'in:daily,weekly,monthly,yearly,on-demand',
            'schedule_config' => 'nullable|array',
            'time_of_day' => 'nullable|string|date_format:H:i',
            'timezone' => 'nullable|string',
            'included_sections' => 'nullable|array',
            'delivery_settings' => 'nullable|array',
            'report_options' => 'nullable|array',
            'active' => 'boolean',
            'customer_id' => 'sometimes|integer|exists:customers,customer_id',
        ];
    }
}
