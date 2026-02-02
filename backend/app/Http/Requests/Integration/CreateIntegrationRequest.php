<?php

declare(strict_types=1);

namespace App\Http\Requests\Integration;

use App\Services\IntegrationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(IntegrationService $integrationService): array
    {
        $availableTypes = $integrationService->scanAvailableIntegrations();

        return [
            'integration_type' => ['required', 'string', Rule::in($availableTypes)],
            'display_name' => 'required|string|min:2|max:255|regex:/^[A-Za-z0-9\s\-\_\.]+$/',
            'status' => ['sometimes', 'string', Rule::in(['active', 'disconnected', 'error', 'importing_historical'])],
            'credentials' => 'nullable|array',
            'configuration' => 'nullable|array',
            'is_source_of_truth' => 'nullable|boolean',
        ];
    }
}
