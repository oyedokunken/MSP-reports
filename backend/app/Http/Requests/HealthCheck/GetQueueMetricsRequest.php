<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthCheck;

use Illuminate\Foundation\Http\FormRequest;

class GetQueueMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
