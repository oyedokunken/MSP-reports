<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthCallbackRequest extends FormRequest
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
            'code' => 'sometimes|string',
            'state' => 'sometimes|string',
            'error' => 'sometimes|string',
            'error_description' => 'sometimes|string',
        ];
    }
}
