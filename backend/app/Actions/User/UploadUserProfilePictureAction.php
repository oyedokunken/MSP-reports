<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class UploadUserProfilePictureAction
{
    /**
     * @throws ValidationException
     */
    public function execute(User $authUser, int $userId, UploadedFile $file): User
    {
        $user = User::where('user_id', $userId)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'user' => ['User not found in your company.'],
            ]);
        }

        // Delete old profile picture if exists
        if ($user->profile_picture) {
            if (Storage::disk('public')->exists($user->profile_picture)) {
                Storage::disk('public')->delete($user->profile_picture);
            }
        }

        // Store new profile picture
        $filename = 'profile_' . $user->user_id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('profile-pictures', $filename, 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'profile_picture' => ['Failed to store profile picture.'],
            ]);
        }

        $user->profile_picture = $path;
        $user->save();

        return $user;
    }
}
