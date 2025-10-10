<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // You can restrict to guests if needed
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'nullable|string',
            'auth_method' => 'required|in:local,saml,m365,ldap',
        ];
    }
}
