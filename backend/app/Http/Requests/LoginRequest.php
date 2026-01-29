<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'nullable|string',
            'auth_method' => 'required|in:local,saml,m365,ldap,keycloak,oidc',
        ];
    }
}
