/**
 * Custom hook for permission-based operations
 * Centralizes permission checking logic to reduce duplication
 */

import { useCallback } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { useToast } from '@/components/ui/Toast'
import { hasPermission } from '@/utils/permissions'

type PermissionSection = 'integrations' | 'customers' | 'reports' | 'users' | 'company_settings'
type PermissionAction = 'view' | 'manage' | 'sync' | 'edit' | 'map' | 'configure' | 'generate' | 'download'

interface UsePermissionOptions {
    /** Custom error message to display */
    errorMessage?: string
    /** Whether to show a toast on permission denied */
    showToast?: boolean
}

/**
 * Hook that provides permission checking with automatic toast notifications
 * 
 * @example
 * const { checkPermission, canPerform } = usePermission()
 * 
 * // Check and show toast on failure
 * const handleAdd = () => {
 *   if (!checkPermission('integrations', 'manage')) return
 *   // proceed with action
 * }
 * 
 * // Just check without side effects
 * const canEdit = canPerform('customers', 'edit')
 */
export function usePermission(defaultOptions?: UsePermissionOptions) {
    const { user } = useAuth()
    const toast = useToast()

    /**
     * Check if user has permission, with optional toast notification
     * Returns true if permitted, false otherwise
     */
    const checkPermission = useCallback(
        (
            section: PermissionSection,
            action: PermissionAction,
            options?: UsePermissionOptions
        ): boolean => {
            const opts = { ...defaultOptions, ...options }
            const permitted = hasPermission(user, section, action)

            if (!permitted && opts?.showToast !== false) {
                toast.error(
                    'Permission Denied',
                    opts?.errorMessage || `You don't have permission to perform this action`
                )
            }

            return permitted
        },
        [user, toast, defaultOptions]
    )

    /**
     * Silent permission check - no toast, just returns boolean
     */
    const canPerform = useCallback(
        (section: PermissionSection, action: PermissionAction): boolean => {
            return hasPermission(user, section, action)
        },
        [user]
    )

    /**
     * HOF that wraps a callback with permission check
     * If permission denied, action won't execute
     */
    const withPermission = useCallback(
        <T extends (...args: unknown[]) => unknown>(
            section: PermissionSection,
            action: PermissionAction,
            callback: T,
            options?: UsePermissionOptions
        ): ((...args: Parameters<T>) => ReturnType<T> | undefined) => {
            return (...args: Parameters<T>) => {
                if (!checkPermission(section, action, options)) {
                    return undefined
                }
                return callback(...args) as ReturnType<T>
            }
        },
        [checkPermission]
    )

    return {
        checkPermission,
        canPerform,
        withPermission,
    }
}

export default usePermission
