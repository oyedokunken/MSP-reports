<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function authLogin(LoginRequest $request)
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])
                    ->where('auth_method', $validated['auth_method'])
                    ->with('company', 'role')
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found or auth method mismatch.'],
            ]);
        }

        if (in_array($user->company->status, ['suspended'])) {
            throw ValidationException::withMessages([
                'email' => ['Your company account is currently suspended. Please contact support.'],
            ]);
        }

        if (in_array($user->status, ['inactive', 'locked'])) {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently ' . $user->status . '. Please contact your administrator.'],
            ]);
        }

        if ($validated['auth_method'] === 'local') {
            if (
                empty($validated['password']) ||
                !Hash::check($validated['password'], $user->password)
            ) {
                throw ValidationException::withMessages([
                    'password' => ['Invalid password.'],
                ]);
            }
        }

        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        AuditLog::create([
            'company_id'  => $user->company_id,
            'event_type'  => 'login',
            'actor_type'  => 'User',
            'actor_id'    => $user->user_id,
            'event_data'  => json_encode([
                'email' => $user->email,
                'auth_method' => $user->auth_method,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'company' => $user->company,
                'token' => $token,
            ],
        ]);
    }

    public function authLogout(Request $request)
    {
        $user = $request->user();

        AuditLog::create([
            'company_id'  => $user->company_id,
            'event_type'  => 'logout',
            'actor_type'  => 'User',
            'actor_id'    => $user->user_id,
            'event_data'  => json_encode([
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        $user->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'company');

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }
}
