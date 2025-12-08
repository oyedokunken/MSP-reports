/**
 * User API types
 * Matches backend UserController response structure
 */

import type { ApiSuccessResponse } from './common'

export interface Role {
    id: number
    role_id: number
    name: string
    description?: string
}

export interface UserPermissions {
    integrations: {
        view: boolean
        manage: boolean
        sync: boolean
    }
    customers: {
        view: boolean
        edit: boolean
        map: boolean
    }
    reports: {
        view: boolean
        configure: boolean
        generate: boolean
        download: boolean
    }
    users: {
        view: boolean
        manage: boolean
    }
    company_settings: {
        view: boolean
        edit: boolean
    }
}

export interface User {
    user_id: number
    company_id: number
    role_id: number
    name: string
    email: string
    status: 'active' | 'inactive' | 'locked'
    auth_method: 'local' | 'saml' | 'm365' | 'ldap'
    permissions: UserPermissions
    role?: Role
    last_login?: string | null
    created_at?: string
    updated_at?: string
}

// API Response Types
export type UserListResponse = ApiSuccessResponse<User[]>

export interface CreateUserRequest {
    name: string
    email: string
    password: string
    role_id: number
    status?: 'active' | 'inactive' | 'locked'
    auth_method?: 'local' | 'saml' | 'm365' | 'ldap'
    permissions?: Partial<UserPermissions>
}

export type CreateUserResponse = ApiSuccessResponse<User>

export interface UpdateUserRequest {
    name?: string
    email?: string
    role_id?: number
}

export type UpdateUserResponse = ApiSuccessResponse<User>

export interface UpdatePermissionsRequest {
    permissions: Partial<UserPermissions>
}

export type UpdatePermissionsResponse = ApiSuccessResponse<User>

export interface ResetPasswordRequest {
    new_password: string
}

export type ResetPasswordResponse = ApiSuccessResponse<{ message: string }>

export type DeactivateUserResponse = ApiSuccessResponse<User>
export type ReactivateUserResponse = ApiSuccessResponse<User>
export type DeleteUserResponse = ApiSuccessResponse<{ message: string }>
