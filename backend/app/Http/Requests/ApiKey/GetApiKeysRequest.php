<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiKey;

use Illuminate\Foundation\Http\FormRequest;

class GetApiKeysRequest extends FormRequest
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
            'search' => 'sometimes|nullable|string|max:100',
            'customer_id' => 'sometimes|nullable|integer|exists:customers,customer_id',
        ];
    }
}
