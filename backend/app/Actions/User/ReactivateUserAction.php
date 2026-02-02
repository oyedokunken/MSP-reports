<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReactivateUserAction
{
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

        $user->status = 'active';
        $user->save();

        return $user;
    }
}
