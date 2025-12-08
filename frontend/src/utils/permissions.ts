import type { User } from '@/types/auth'

export function isAdmin(user: User | null): boolean {
    if (!user) return false
    return user.role_id === 1 || user.role?.name === 'Admin'
}

export function hasPermission(
    user: User | null,
    section: string,
    action: string
): boolean {
    // Admins bypass all permission checks
    if (isAdmin(user)) return true

    // Check specific permission
    const permissions = user?.permissions as any
    return permissions?.[section]?.[action] === true
}
