<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthCheck;

use Illuminate\Foundation\Http\FormRequest;

class GetMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => 'nullable|integer|min:1|max:365',
            'months' => 'nullable|integer|min:1|max:24',
        ];
    }
}
