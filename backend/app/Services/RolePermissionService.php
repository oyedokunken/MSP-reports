<?php

namespace App\Services;

class RolePermissionService
{
    /**
     * Get default permissions for a given role
     *
     * @param string $roleName
     * @return array
     */
    public static function getDefaultPermissions(string $roleName): array
    {
        return match (strtolower($roleName)) {
            'admin' => self::getAdminPermissions(),
            'manager' => self::getManagerPermissions(),
            'technician' => self::getTechnicianPermissions(),
            'viewer' => self::getViewerPermissions(),
            default => self::getViewerPermissions(), // Default to most restrictive
        };
    }

    /**
     * Admin - Full access to everything
     */
    private static function getAdminPermissions(): array
    {
        return [
            'integrations' => [
                'view' => true,
                'manage' => true,
                'sync' => true,
            ],
            'customers' => [
                'view' => true,
                'edit' => true,
                'map' => true,
            ],
            'reports' => [
                'view' => true,
                'configure' => true,
                'generate' => true,
                'download' => true,
            ],
            'users' => [
                'view' => true,
                'manage' => true,
            ],
            'company_settings' => [
                'view' => true,
                'edit' => true,
            ],
        ];
    }

    /**
     * Manager - Report configuration and analytics, customer management
     */
    private static function getManagerPermissions(): array
    {
        return [
            'integrations' => [
                'view' => true,
                'manage' => false,
                'sync' => false,
            ],
            'customers' => [
                'view' => true,
                'edit' => true,
                'map' => false,
            ],
            'reports' => [
                'view' => true,
                'configure' => true,
                'generate' => true,
                'download' => true,
            ],
            'users' => [
                'view' => true,
                'manage' => false,
            ],
            'company_settings' => [
                'view' => true,
                'edit' => false,
            ],
        ];
    }

    /**
     * Technician - Integration and sync management, limited customer access
     */
    private static function getTechnicianPermissions(): array
    {
        return [
            'integrations' => [
                'view' => true,
                'manage' => true,
                'sync' => true,
            ],
            'customers' => [
                'view' => true,
                'edit' => false,
                'map' => true,
            ],
            'reports' => [
                'view' => true,
                'configure' => false,
                'generate' => false,
                'download' => true,
            ],
            'users' => [
                'view' => false,
                'manage' => false,
            ],
            'company_settings' => [
                'view' => false,
                'edit' => false,
            ],
        ];
    }

    /**
     * Viewer - Read-only report access
     */
    private static function getViewerPermissions(): array
    {
        return [
            'integrations' => [
                'view' => false,
                'manage' => false,
                'sync' => false,
            ],
            'customers' => [
                'view' => true,
                'edit' => false,
                'map' => false,
            ],
            'reports' => [
                'view' => true,
                'configure' => false,
                'generate' => false,
                'download' => true,
            ],
            'users' => [
                'view' => false,
                'manage' => false,
            ],
            'company_settings' => [
                'view' => false,
                'edit' => false,
            ],
        ];
    }

    /**
     * Check if a user has a specific permission
     *
     * @param array|null $userPermissions
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public static function hasPermission(?array $userPermissions, string $resource, string $action): bool
    {
        if (!$userPermissions) {
            return false;
        }

        return $userPermissions[$resource][$action] ?? false;
    }

    /**
     * Merge role permissions with custom user permissions
     * Custom permissions override role defaults
     *
     * @param string $roleName
     * @param array|null $customPermissions
     * @return array
     */
    public static function mergePermissions(string $roleName, ?array $customPermissions): array
    {
        $rolePermissions = self::getDefaultPermissions($roleName);

        if (!$customPermissions) {
            return $rolePermissions;
        }

        // Deep merge - custom permissions override role defaults
        return array_replace_recursive($rolePermissions, $customPermissions);
    }
}
