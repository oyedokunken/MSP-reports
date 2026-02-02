<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Mail\PasswordResetMail;
use App\Models\AuditLog;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class RequestPasswordResetAction
{
    /**
     * Execute the password reset request action.
     *
     * @param array{email: string} $data
     * @return array{success: bool, message: string, debug_token?: string|null}
     */
    public function execute(array $data, ?string $ipAddress, ?string $userAgent): array
    {
        $email = trim(strtolower($data['email']));

        $user = User::where('email', $email)
            ->where('auth_method', 'local')
            ->first();

        if (!$user) {
            return [
                'success' => true,
                'message' => 'If an account exists with this email, a password reset link has been sent.',
            ];
        }

        // Delete existing tokens
        PasswordResetToken::where('email', $email)->delete();

        // Generate new token
        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);

        PasswordResetToken::create([
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        // Send email
        $this->sendResetEmail($user, $token);

        // Audit log
        $this->logAuditEvent($user, $ipAddress, $userAgent);

        return [
            'success' => true,
            'message' => 'If an account exists with this email, a password reset link has been sent.',
            'debug_token' => config('app.debug') ? $token : null,
        ];
    }

    private function sendResetEmail(User $user, string $token): void
    {
        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
        } catch (\Exception $e) {
            Log::error('Password reset email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function logAuditEvent(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_requested',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'email' => $user->email,
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
