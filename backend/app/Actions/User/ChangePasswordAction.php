<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordAction
{
    /**
     * @throws ValidationException
     */
    public function execute(User $user, string $currentPassword, string $newPassword, string $ip, string $userAgent): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->force_password_change = false;
        $user->save();

        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'user_password_changed_first_login',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => [
                'user_email' => $user->email,
            ],
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
