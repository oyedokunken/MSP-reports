<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class BulkGenerateReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids' => 'required|array|min:1|max:100',
            'customer_ids.*' => 'required|integer',
            'report_type' => 'nullable|string',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'preview_only' => 'nullable|boolean',
        ];
    }
}
