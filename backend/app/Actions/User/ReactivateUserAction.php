<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Concerns\LogsAudit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReactivateUserAction
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

        $user->status = \App\Enums\UserStatus::ACTIVE;
        $user->save();

        $this->audit($authUser->company_id, 'user_reactivated', $user, [
            'user_name' => $user->name,
            'user_email' => $user->email,
        ]);

        return $user;
    }
}
