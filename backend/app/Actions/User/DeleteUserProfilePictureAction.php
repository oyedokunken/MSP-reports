<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteUserProfilePictureAction
{
    /**
     * @throws ValidationException
     */
    public function execute(User $authUser, int $userId): void
    {
        $user = User::where('user_id', $userId)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
             // If user not found, we effectively "did nothing" which is fine, 
             // or we could throw. Ideally, validation handles existence.
             // Matching Controller logic which checks "if (!$user)" basically.
             throw ValidationException::withMessages([
                'user' => ['User not found in your company.'],
            ]);
        }

        if ($user->profile_picture) {
            if (Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }
            $user->profile_picture = null;
            $user->save();
        }
    }
}
