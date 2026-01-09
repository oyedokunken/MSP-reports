/**
 * Type-safe API client for User endpoints
 * Provides strongly-typed methods for all user-related API calls
 */

import { api } from '@/lib/api'
import type {
    UserListResponse,
    CreateUserRequest,
    CreateUserResponse,
    UpdateUserRequest,
    UpdateUserResponse,
    UpdatePermissionsRequest,
    UpdatePermissionsResponse,
    ResetPasswordRequest,
    ResetPasswordResponse,
    DeactivateUserResponse,
    ReactivateUserResponse,
    DeleteUserResponse,
    Role,
} from '@/types/api/user'
import type { ApiSuccessResponse } from '@/types/api/common'

export const userApi = {
    /**
     * Get all users with pagination
     * GET /api/users?page={page}&per_page={perPage}
     */
    async getUsers(page: number = 1, perPage: number = 10): Promise<UserListResponse> {
        return api.get(`/users?page=${page}&per_page=${perPage}`) as unknown as Promise<UserListResponse>
    },

    /**
     * Create a new user
     * POST /api/users
     */
    async createUser(data: CreateUserRequest): Promise<CreateUserResponse> {
        return api.post('/users', data) as unknown as Promise<CreateUserResponse>
    },

    /**
     * Update user details
     * PATCH /api/users/{id}
     */
    async updateUser(userId: number, data: UpdateUserRequest): Promise<UpdateUserResponse> {
        return api.patch(`/users/${userId}`, data) as unknown as Promise<UpdateUserResponse>
    },

    /**
     * Update user permissions
     * PATCH /api/users/{id}/permissions
     */
    async updatePermissions(
        userId: number,
        data: UpdatePermissionsRequest
    ): Promise<UpdatePermissionsResponse> {
        return api.patch(`/users/${userId}/permissions`, data) as unknown as Promise<UpdatePermissionsResponse>
    },

    /**
     * Deactivate a user
     * PATCH /api/users/{id}/deactivate
     */
    async deactivateUser(userId: number): Promise<DeactivateUserResponse> {
        return api.patch(`/users/${userId}/deactivate`) as unknown as Promise<DeactivateUserResponse>
    },

    /**
     * Reactivate a user
     * PATCH /api/users/{id}/reactivate
     */
    async reactivateUser(userId: number): Promise<ReactivateUserResponse> {
        return api.patch(`/users/${userId}/reactivate`) as unknown as Promise<ReactivateUserResponse>
    },

    /**
     * Delete a user
     * DELETE /api/users/{id}
     */
    async deleteUser(userId: number): Promise<DeleteUserResponse> {
        return api.delete(`/users/${userId}`) as unknown as Promise<DeleteUserResponse>
    },

    /**
     * Reset user password (admin action)
     * POST /api/users/{id}/reset-password
     */
    async resetPassword(userId: number, data: ResetPasswordRequest): Promise<ResetPasswordResponse> {
        return api.post(`/users/${userId}/reset-password`, data) as unknown as Promise<ResetPasswordResponse>
    },

    /**
     * Get all available roles
     * GET /api/users/roles
     */
    async getRoles(): Promise<ApiSuccessResponse<Role[]>> {
        return api.get('/users/roles') as unknown as Promise<ApiSuccessResponse<Role[]>>
    },
}
