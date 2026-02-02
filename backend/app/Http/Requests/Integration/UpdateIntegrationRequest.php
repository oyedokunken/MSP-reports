<?php

declare(strict_types=1);

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationRequest extends FormRequest
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
            'display_name' => 'sometimes|string|min:2|max:255|regex:/^[A-Za-z0-9\s\-\_\.]+$/',
            'status' => ['sometimes', 'string', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
            'credentials' => 'sometimes|array',
            'configuration' => 'sometimes|array',
        ];
    }
}
