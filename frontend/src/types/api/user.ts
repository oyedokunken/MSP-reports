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
    schedules: {
        view: boolean
        create: boolean
        edit: boolean
        delete: boolean
    }
    templates: {
        view: boolean
        create: boolean
        edit: boolean
        delete: boolean
    }
    users: {
        view: boolean
        manage: boolean
    }
    api_keys: {
        view: boolean
        create: boolean
        revoke: boolean
    }
    licenses: {
        view: boolean
        manage: boolean
    }
    monitoring: {
        view: boolean
        configure: boolean
    }
    notifications: {
        view: boolean
        configure: boolean
    }
    security: {
        view_logs: boolean
        configure: boolean
    }
    exchange: {
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
    scoped_customers?: number[] | null
    force_password_change?: boolean
    profile_picture?: string | null
    role?: Role
    last_login?: string | null
    created_at?: string
    updated_at?: string
}

export interface UserListResponse extends ApiSuccessResponse<User[]> {
    pagination?: {
        current_page: number
        per_page: number
        total: number
        last_page: number
        from: number | null
        to: number | null
    }
}

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
