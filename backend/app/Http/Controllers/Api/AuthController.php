<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function __construct()
    {
        // Example: apply middleware to secure other methods if needed
        $this->middleware('auth:sanctum')->only(['logout']);
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])
                    ->where('auth_method', $validated['auth_method'])
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found or auth method mismatch.'],
            ]);
        }

        switch ($validated['auth_method']) {
            case 'local':
                if (!$validated['password'] || !Hash::check($validated['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'password' => ['Invalid password.'],
                    ]);
                }
                break;

            case 'saml':
            case 'm365':
            case 'ldap':
                // Placeholder for external auth logic
                break;
        }

        $user->last_login_at = now();
        $user->save();

        // Generate API token via Sanctum
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
