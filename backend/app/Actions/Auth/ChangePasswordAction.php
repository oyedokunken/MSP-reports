<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordAction
{
    /**
     * Execute the password change action.
     *
     * @param array{current_password: string, new_password: string} $data
     * @throws ValidationException
     */
    public function execute(User $user, array $data, ?string $ipAddress, ?string $userAgent): void
    {
        if ($user->auth_method !== \App\Enums\AuthMethod::LOCAL) {
            throw ValidationException::withMessages([
                'auth_method' => ['Password changes are not available for ' . $user->auth_method->value . ' authentication.'],
            ]);
        }

        if (!Hash::check($data['current_password'], $user->password ?? '')) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        $this->logAuditEvent($user, $ipAddress, $userAgent);
    }

    private function logAuditEvent(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'password_changed',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'event_data' => json_encode([
                'timestamp' => now()->toDateTimeString(),
            ]),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
