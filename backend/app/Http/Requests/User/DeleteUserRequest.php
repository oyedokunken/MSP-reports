<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authUser = $this->user();
        $targetUserId = $this->route('id');

        return User::where('company_id', $authUser->company_id)
            ->where('user_id', $targetUserId)
            ->exists();
    }

    public function rules(): array
    {
        return [];
    }
}
