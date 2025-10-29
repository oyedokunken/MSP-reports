<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PasswordResetToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\LoginRequest;
use App\Mail\PasswordResetMail;

class AuthController extends Controller
{
    public function authLogin(LoginRequest $request)
    {
        $validated = $request->validated();
        
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['auth_method'] = trim($validated['auth_method']);

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
        
        $sanitisedUser = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $sanitisedUser,
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
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('role', 'company');
        
        $sanitisedUser = $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'status' => 'success',
            'data' => $sanitisedUser,
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = trim(strtolower($validated['email']));
        
        $user = User::where('email', $email)
                    ->where('auth_method', 'local')
                    ->first();
        
        if (!$user) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with this email, a password reset link has been sent.',
            ]);
        }
        
        PasswordResetToken::where('email', $email)->delete();
        
        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);
        
        PasswordResetToken::create([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        } catch (\Exception $e) {
            \Log::error('Password reset email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_requested',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an account exists with this email, a password reset link has been sent.',
            
            'debug_token' => config('app.debug') ? $token : null,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = trim(strtolower($validated['email']));
        $hashedToken = hash('sha256', $validated['token']);

        $resetToken = PasswordResetToken::where('email', $email)
                                        ->where('token', $hashedToken)
                                        ->valid()
                                        ->first();

        if (!$resetToken) {
            throw ValidationException::withMessages([
                'token' => ['Invalid or expired password reset token.'],
            ]);
        }

        $user = User::where('email', $email)
                    ->where('auth_method', 'local')
                    ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found.'],
            ]);
        }
        
        $user->password = Hash::make($validated['password']);
        $user->save();
        
        $resetToken->delete();

        $user->tokens()->delete();
        
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_completed',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),  
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        if ($user->auth_method !== 'local') {
            return response()->json([
                'status' => 'error',
                'message' => 'Password changes are not available for ' . $user->auth_method . ' authentication.',
            ], 400);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }
        
        $user->password = Hash::make($validated['new_password']);
        $user->save();
        
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_changed',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.',
        ]);
    }
}
