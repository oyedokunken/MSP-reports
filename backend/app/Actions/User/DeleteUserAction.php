<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Concerns\LogsAudit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DeleteUserAction
{
    use LogsAudit;

    /**
     * @throws ValidationException
     */
    public function execute(User $authUser, int $userId): void
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
                'user' => ['You cannot delete your own account.'],
            ]);
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $userIdVal = $user->user_id;

        $user->tokens()->delete();
        $user->delete();

        $this->audit($authUser->company_id, 'user_deleted', null, [
            'deleted_user_id' => $userIdVal,
            'user_name' => $userName,
            'user_email' => $userEmail,
        ]);
    }
}
