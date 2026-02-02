<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'sometimes|in:active,inactive,locked',
            'auth_method' => 'sometimes|in:local,saml,m365,ldap',
            'permissions' => 'nullable|array',
            'scoped_customers' => 'nullable|array',
            'force_password_change' => 'sometimes|boolean',
        ];
    }
}
