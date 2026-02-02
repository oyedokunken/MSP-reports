<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class BatchAutoMatchRequest extends FormRequest
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
            'integration_customer_ids' => 'sometimes|array|max:100',
            'integration_customer_ids.*' => 'integer',
            'auto_apply' => 'sometimes|boolean',
            'min_confidence' => 'sometimes|integer|min:50|max:100',
        ];
    }
}
