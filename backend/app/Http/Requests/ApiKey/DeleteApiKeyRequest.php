<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiKey;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class DeleteApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $apiKeyId = $this->route('id');

        return ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $apiKeyId)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
