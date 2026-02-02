<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportTemplate;

use Illuminate\Foundation\Http\FormRequest;

class GetReportTemplatesRequest extends FormRequest
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
            'active' => 'nullable|string|in:true,false,1,0',
            'global' => 'nullable|string|in:true,false,1,0',
        ];
    }
}
