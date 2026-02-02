<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $authUser = $this->user();
        $targetUserId = $this->route('id');

        return \App\Models\User::where('company_id', $authUser->company_id)
            ->where('user_id', $targetUserId)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];
    }
}
