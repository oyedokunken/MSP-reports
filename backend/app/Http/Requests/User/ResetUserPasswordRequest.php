<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', new StrongPassword()],
        ];
    }
}
