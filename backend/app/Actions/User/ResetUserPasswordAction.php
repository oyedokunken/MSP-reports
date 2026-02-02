<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ResetUserPasswordAction
{
    /**
     * @throws ValidationException
     */
    public function execute(
        User $authUser,
        int $userId,
        string $newPassword,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $user = User::where('user_id', $userId)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['User not found in your company.'],
            ]);
        }

        if ($user->auth_method !== \App\Enums\AuthMethod::LOCAL) {
            throw ValidationException::withMessages([
                'auth_method' => ['Password reset is only available for local authentication users.'],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $user->tokens()->delete();

        AuditLog::create([
            'company_id' => $authUser->company_id,
            'event_type' => 'user_password_reset_by_admin',
            'actor_type' => 'User',
            'actor_id' => $authUser->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
            ]),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
