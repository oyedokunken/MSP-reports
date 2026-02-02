<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportTemplate;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableReportTypesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,customer_id',
        ];
    }
}
