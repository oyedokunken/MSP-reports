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
    if (isAdmin(user)) return true

    const permissions = user?.permissions as Record<string, Record<string, boolean>> | undefined
    return permissions?.[section]?.[action] === true
}
