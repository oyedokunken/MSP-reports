<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeactivateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\ReactivateUserAction;
use App\Actions\User\ResetUserPasswordAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Requests\User\ClearForcePasswordChangeRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\UpdatePermissionsRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UploadProfilePictureRequest;
use App\Http\Requests\User\GetUsersRequest;
use App\Http\Requests\User\DeactivateUserRequest;
use App\Http\Requests\User\ReactivateUserRequest;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\GetRolesRequest;
use App\Http\Requests\User\DeleteProfilePictureRequest;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserController extends Controller
{
    public function getUser(GetUsersRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;

        $query = User::where('company_id', $authUser->company_id)->with('role');
        
        if (!empty($validated['search'])) {
            $query->where(function($q) use ($validated) {
                $q->where('name', 'like', '%' . $validated['search'] . '%')
                  ->orWhere('email', 'like', '%' . $validated['search'] . '%');
            });
        }

        if (!empty($validated['role_id'])) {
            $query->where('role_id', $validated['role_id']);
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);

        $users->getCollection()->transform(function ($user) {
            return $user->makeHidden(['password']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        ]);
    }

    public function createUser(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $user->makeHidden(['password'])->load('role')
            ], 201);
        } catch (ValidationException $e) {
            // Check if it's a license error
            if (isset($e->validator->failed()['license'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 403);
            }
            throw $e;
        }
    }

    public function updatePermissions(UpdatePermissionsRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
            ->where('company_id', $authUser->company_id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found in your company.'
            ], 404);
        }

        $validated = $request->validated();

        $defaultPermissions = [
            "integrations" => ["view" => true, "manage" => true, "sync" => true],
            "customers" => ["view" => true, "edit" => true, "map" => true],
            "reports" => ["view" => true, "configure" => true, "generate" => true, "download" => true],
            "users" => ["view" => true, "manage" => true],
            "company_settings" => ["view" => true, "edit" => true]
        ];

        $permissions = $validated['permissions'];
        foreach ($defaultPermissions as $section => $actions) {
            $permissions[$section] = array_merge($actions, $permissions[$section] ?? []);
        }

        $user->permissions = $permissions;
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => $user->makeHidden(['password'])->load('role')
        ]);
    }

    public function updateUser(UpdateUserRequest $request, int $id, UpdateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id, $request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $user->makeHidden(['password'])->load('role')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function deactivateUser(DeactivateUserRequest $request, int $id, DeactivateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id);
            return response()->json([
                'status' => 'success',
                'message' => 'User deactivated successfully.',
                'data' => $user->makeHidden(['password'])->load('role')
            ]);
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function reactivateUser(ReactivateUserRequest $request, int $id, ReactivateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id);
            return response()->json([
                'status' => 'success',
                'message' => 'User reactivated successfully.',
                'data' => $user->makeHidden(['password'])->load('role')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function deleteUser(DeleteUserRequest $request, int $id, DeleteUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $action->execute($authUser, $id);
            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully.'
            ]);
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function resetUserPassword(ResetUserPasswordRequest $request, int $id, ResetUserPasswordAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $action->execute($authUser, $id, $request->validated()['new_password'], $request->ip(), $request->userAgent());
            return response()->json([
                'status' => 'success',
                'message' => 'User password reset successfully.'
            ]);
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function getRoles(GetRolesRequest $request): JsonResponse
    {
        $roles = Role::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function uploadProfilePicture(UploadProfilePictureRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
            ->where('company_id', $authUser->company_id)
            ->first();

        // No need for "if (!$user)" because authorize() handles it (if we updated UploadProfilePictureRequest)
        // Let's check if UploadProfilePictureRequest has authorization. 
        // If not, we should keep the check here or update it.

        // Delete old profile picture if exists
        if ($user->profile_picture) {
            $oldPath = storage_path('app/public/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Store new profile picture
        $file = $request->file('profile_picture');
        $filename = 'profile_' . $user->user_id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('profile-pictures', $filename, 'public');

        if ($path === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to store profile picture.',
            ], 500);
        }

        $user->profile_picture = $path;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile picture uploaded successfully.',
            'data' => [
                'profile_picture' => $path,
                'profile_picture_url' => asset('storage/' . $path),
            ]
        ]);
    }

    public function deleteProfilePicture(DeleteProfilePictureRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        $user = User::where('user_id', $id)
            ->where('company_id', $authUser->company_id)
            ->first();

        // No need for "if (!$user)" because authorize() handles it

        if ($user->profile_picture) {
            $oldPath = storage_path('app/public/' . $user->profile_picture);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            $user->profile_picture = null;
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Profile picture deleted successfully.'
        ]);
    }

    public function clearForcePasswordChange(ClearForcePasswordChangeRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect.'
            ], 400);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->force_password_change = false;
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        AuditLog::create([
            'company_id' => $user->company_id,
            'event_type' => 'user_password_changed_first_login',
            'actor_type' => 'User',
            'actor_id' => $user->user_id,
            'target_type' => 'User',
            'target_id' => $user->user_id,
            'event_data' => json_encode([
                'user_email' => $user->email,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.',
            'data' => [
                'token' => $token,
            ]
        ]);
    }
}
