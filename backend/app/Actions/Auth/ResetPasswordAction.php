<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\AuditLog;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ResetPasswordAction
{
    /**
     * Execute the password reset action.
     *
     * @param array{email: string, token: string, password: string} $data
     * @throws ValidationException
     */
    public function execute(array $data, ?string $ipAddress, ?string $userAgent): void
    {
        $email = trim(strtolower($data['email']));
        $hashedToken = hash('sha256', $data['token']);

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

        // Update password
        $user->password = Hash::make($data['password']);
        $user->save();

        // Cleanup
        $resetToken->delete();
        $user->tokens()->delete();

        // Audit log
        $this->logAuditEvent($user, $ipAddress, $userAgent);
    }

    private function logAuditEvent(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_reset_completed',
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
