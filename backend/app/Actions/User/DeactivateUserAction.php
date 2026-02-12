<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Concerns\LogsAudit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DeactivateUserAction
{
    use LogsAudit;

    /**
     * @throws ValidationException
     */
    public function execute(User $authUser, int $userId): User
    {
        $user = User::where('user_id', $userId)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['User not found in your company.'],
            ]);
        }

        if ($user->user_id === $authUser->user_id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot deactivate your own account.'],
            ]);
        }

        $user->status = \App\Enums\UserStatus::INACTIVE;
        $user->save();
        $user->tokens()->delete();

        $this->audit($authUser->company_id, 'user_deactivated', $user, [
            'user_name' => $user->name,
            'user_email' => $user->email,
        ]);

        return $user;
    }
}
