<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\User\CreateUserAction;
use App\Actions\User\DeactivateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\DeleteUserProfilePictureAction;
use App\Actions\User\GetUsersAction;
use App\Actions\User\ReactivateUserAction;
use App\Actions\User\ResetUserPasswordAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\UpdateUserPermissionsAction;
use App\Actions\User\UploadUserProfilePictureAction;
use App\Http\Requests\User\ClearForcePasswordChangeRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\DeactivateUserRequest;
use App\Http\Requests\User\DeleteProfilePictureRequest;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\GetRolesRequest;
use App\Http\Requests\User\GetUsersRequest;
use App\Http\Requests\User\ReactivateUserRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\UpdatePermissionsRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UploadProfilePictureRequest;
use App\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserController extends Controller
{
    public function getUser(GetUsersRequest $request, GetUsersAction $action): JsonResponse
    {
        $authUser = $request->user();
        $users = $action->execute($authUser, $request->validated());

        return $this->success(UserResource::collection($users->items()), null, 200, [
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
            /** @var array{name: string, email: string, password: string, role_id: int, status?: string, auth_method?: string, permissions?: array<string, mixed>|null, scoped_customers?: array<int>|null} $validated */
            $validated = $request->validated();
            $user = $action->execute($authUser, $validated);
            return $this->success(new UserResource($user->load('role')), null, 201);
        } catch (ValidationException $e) {
            // Check if it's a license error
            if (str_contains($e->getMessage(), 'license')) {
                return $this->error($e->getMessage(), 403);
            }
            return $this->error($e->getMessage(), 422);
        }
    }

    public function updatePermissions(UpdatePermissionsRequest $request, int $id, UpdateUserPermissionsAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            /** @var array{permissions: array<string, array<string, bool>>} $validated */
            $validated = $request->validated();
            $user = $action->execute($authUser, $id, $validated);
            
            return $this->success(new UserResource($user->load('role')));
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function updateUser(UpdateUserRequest $request, int $id, UpdateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id, $request->validated());
            // Standardized response using success helper and Resource
            return $this->success(new UserResource($user->load('role')));
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }
    }

    public function deactivateUser(DeactivateUserRequest $request, int $id, DeactivateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id);
            return $this->success(new UserResource($user->load('role')), 'User deactivated successfully.');
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    public function reactivateUser(ReactivateUserRequest $request, int $id, ReactivateUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id);
            return $this->success(new UserResource($user->load('role')), 'User reactivated successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function deleteUser(DeleteUserRequest $request, int $id, DeleteUserAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $action->execute($authUser, $id);
            return $this->success([], 'User deleted successfully.');
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    public function resetUserPassword(ResetUserPasswordRequest $request, int $id, ResetUserPasswordAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $action->execute($authUser, $id, $request->validated()['new_password'], $request->ip(), $request->userAgent());
            return $this->success([], 'User password reset successfully.');
        } catch (ValidationException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    public function getRoles(GetRolesRequest $request): JsonResponse
    {
        $roles = Role::select('id', 'name')->get();
        return $this->success($roles);
    }

    public function uploadProfilePicture(UploadProfilePictureRequest $request, int $id, UploadUserProfilePictureAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $user = $action->execute($authUser, $id, $request->file('profile_picture'));
            
            return $this->success([
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => $user->profile_picture_url,
            ], 'Profile picture uploaded successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    public function deleteProfilePicture(DeleteProfilePictureRequest $request, int $id, DeleteUserProfilePictureAction $action): JsonResponse
    {
        $authUser = $request->user();

        try {
            $action->execute($authUser, $id);
            return $this->success([], 'Profile picture deleted successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function clearForcePasswordChange(ClearForcePasswordChangeRequest $request, \App\Actions\User\ChangePasswordAction $action): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $action->execute(
                $user,
                $validated['current_password'],
                $validated['new_password'],
                $request->ip() ?? '',
                $request->userAgent() ?? ''
            );

            $user->tokens()->delete();
            $token = $user->createToken('api-token')->plainTextToken;

            return $this->success([
                'token' => $token,
            ], 'Password changed successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
