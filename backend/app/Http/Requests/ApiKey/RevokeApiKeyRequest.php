<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiKey;

use App\Models\ApiKey;
use Illuminate\Foundation\Http\FormRequest;

class RevokeApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $apiKeyId = $this->route('id');

        return ApiKey::where('company_id', $user->company_id)
            ->where('api_key_id', $apiKeyId)
            ->exists();
    }

    public function rules(): array
    {
        return [];
    }
}
