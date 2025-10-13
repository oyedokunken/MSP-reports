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

        if ($validated['auth_method'] === 'local') {
            if (!$validated['password'] || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password.'],
                ]);
            }
        }

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user->load('role'),
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
