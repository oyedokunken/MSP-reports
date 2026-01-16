import { describe, it, expect } from 'vitest'
import { isAdmin, hasPermission } from './permissions'
import type { User } from '@/types/auth'

const createUser = (overrides: Partial<User> = {}): User => ({
    user_id: 1,
    name: 'Test User',
    email: 'test@example.com',
    role_id: 2,
    company_id: 1,
    company_name: 'Test Company',
    permissions: {
        integrations: { view: false, manage: false, sync: false },
        customers: { view: false, edit: false, map: false },
        reports: { view: false, configure: false, generate: false, download: false },
        users: { view: false, manage: false },
        company_settings: { view: false, edit: false },
    },
    last_login: null,
    created_at: '2024-01-01T00:00:00Z',
    ...overrides,
})

describe('isAdmin', () => {
    it('returns false for null user', () => {
        expect(isAdmin(null)).toBe(false)
    })

    it('returns true for user with role_id 1', () => {
        const adminUser = createUser({ role_id: 1 })
        expect(isAdmin(adminUser)).toBe(true)
    })

    it('returns true for user with Admin role name', () => {
        const adminUser = createUser({ role: { id: 1, name: 'Admin', slug: 'admin' } })
        expect(isAdmin(adminUser)).toBe(true)
    })

    it('returns false for regular user', () => {
        const regularUser = createUser({ role_id: 2, role: { id: 2, name: 'User', slug: 'user' } })
        expect(isAdmin(regularUser)).toBe(false)
    })
})

describe('hasPermission', () => {
    it('returns true for admin regardless of permission', () => {
        const adminUser = createUser({ role_id: 1 })
        expect(hasPermission(adminUser, 'reports', 'view')).toBe(true)
        expect(hasPermission(adminUser, 'anything', 'anything')).toBe(true)
    })

    it('returns false for null user', () => {
        expect(hasPermission(null, 'reports', 'view')).toBe(false)
    })

    it('returns true when user has specific permission', () => {
        const user = createUser({
            role_id: 2,
            permissions: {
                integrations: { view: false, manage: false, sync: false },
                customers: { view: false, edit: false, map: false },
                reports: { view: true, configure: false, generate: false, download: false },
                users: { view: false, manage: false },
                company_settings: { view: false, edit: false },
            },
        })
        expect(hasPermission(user, 'reports', 'view')).toBe(true)
        expect(hasPermission(user, 'reports', 'configure')).toBe(false)
    })

    it('returns false for missing action in permission', () => {
        const user = createUser({ role_id: 2 })
        expect(hasPermission(user, 'reports', 'view')).toBe(false)
    })
})
