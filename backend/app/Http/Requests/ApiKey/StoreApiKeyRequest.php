<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiKey;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => 'required|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,customer_id',
            'permissions' => 'nullable|array',
            'ip_whitelist' => 'nullable|array',
            'ip_whitelist.*' => 'ip',
            'allowed_endpoints' => 'nullable|array',
            'allowed_endpoints.*' => 'string',
        ];
    }
}
