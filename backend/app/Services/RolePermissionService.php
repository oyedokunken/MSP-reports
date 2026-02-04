<?php

declare(strict_types=1);

namespace App\Services;

final class RolePermissionService
{
    /**
     * Get default permissions for a given role
     *
     * @param string $roleName
     * @return array<string, array<string, bool>>
     */
    public static function getDefaultPermissions(string $roleName): array
    {
        return match (strtolower($roleName)) {
            'admin' => self::getAdminPermissions(),
            'manager' => self::getManagerPermissions(),
            'technician' => self::getTechnicianPermissions(),
            'viewer' => self::getViewerPermissions(),
            'customer' => self::getCustomerPermissions(),
            default => self::getViewerPermissions(), // Default to most restrictive
        };
    }

    /**
     * Admin - Full access to everything
     * @return array<string, array<string, bool>>
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
            'schedules' => [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ],
            'templates' => [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ],
            'users' => [
                'view' => true,
                'manage' => true,
            ],
            'api_keys' => [
                'view' => true,
                'create' => true,
                'revoke' => true,
            ],
            'licenses' => [
                'view' => true,
                'manage' => true,
            ],
            'monitoring' => [
                'view' => true,
                'configure' => true,
            ],
            'notifications' => [
                'view' => true,
                'configure' => true,
            ],
            'security' => [
                'view_logs' => true,
                'configure' => true,
            ],
            'exchange' => [
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
     * @return array<string, array<string, bool>>
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
            'schedules' => [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
            ],
            'templates' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'users' => [
                'view' => true,
                'manage' => false,
            ],
            'api_keys' => [
                'view' => false,
                'create' => false,
                'revoke' => false,
            ],
            'licenses' => [
                'view' => true,
                'manage' => false,
            ],
            'monitoring' => [
                'view' => false,
                'configure' => false,
            ],
            'notifications' => [
                'view' => true,
                'configure' => true,
            ],
            'security' => [
                'view_logs' => false,
                'configure' => false,
            ],
            'exchange' => [
                'view' => false,
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
     * @return array<string, array<string, bool>>
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
            'schedules' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'templates' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'users' => [
                'view' => false,
                'manage' => false,
            ],
            'api_keys' => [
                'view' => true,
                'create' => true,
                'revoke' => false,
            ],
            'licenses' => [
                'view' => false,
                'manage' => false,
            ],
            'monitoring' => [
                'view' => true,
                'configure' => false,
            ],
            'notifications' => [
                'view' => true,
                'configure' => false,
            ],
            'security' => [
                'view_logs' => false,
                'configure' => false,
            ],
            'exchange' => [
                'view' => true,
                'manage' => true,
            ],
            'company_settings' => [
                'view' => false,
                'edit' => false,
            ],
        ];
    }

    /**
     * Viewer - Read-only report access
     * @return array<string, array<string, bool>>
     */
    private static function getViewerPermissions(): array
    {
        return [
            'integrations' => [
                'view' => true,
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
            'schedules' => [
                'view' => true,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'templates' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'users' => [
                'view' => false,
                'manage' => false,
            ],
            'api_keys' => [
                'view' => false,
                'create' => false,
                'revoke' => false,
            ],
            'licenses' => [
                'view' => false,
                'manage' => false,
            ],
            'monitoring' => [
                'view' => false,
                'configure' => false,
            ],
            'notifications' => [
                'view' => true,
                'configure' => false,
            ],
            'security' => [
                'view_logs' => false,
                'configure' => false,
            ],
            'exchange' => [
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
     * Customer - Customer-facing read-only access to own reports
     * @return array<string, array<string, bool>>
     */
    private static function getCustomerPermissions(): array
    {
        return [
            'integrations' => [
                'view' => false,
                'manage' => false,
                'sync' => false,
            ],
            'customers' => [
                'view' => false,
                'edit' => false,
                'map' => false,
            ],
            'reports' => [
                'view' => true,
                'configure' => false,
                'generate' => false,
                'download' => true,
            ],
            'schedules' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'templates' => [
                'view' => false,
                'create' => false,
                'edit' => false,
                'delete' => false,
            ],
            'users' => [
                'view' => false,
                'manage' => false,
            ],
            'api_keys' => [
                'view' => false,
                'create' => false,
                'revoke' => false,
            ],
            'licenses' => [
                'view' => false,
                'manage' => false,
            ],
            'monitoring' => [
                'view' => false,
                'configure' => false,
            ],
            'notifications' => [
                'view' => false,
                'configure' => false,
            ],
            'security' => [
                'view_logs' => false,
                'configure' => false,
            ],
            'exchange' => [
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
     * @param array<string, array<string, bool>>|null $userPermissions
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
     * @param array<string, array<string, bool>>|string|null $customPermissions
     * @return array<string, array<string, bool>>
     */
    public static function mergePermissions(string $roleName, mixed $customPermissions): array
    {
        $rolePermissions = self::getDefaultPermissions($roleName);

        if (is_string($customPermissions)) {
            $decoded = json_decode($customPermissions, true);
            if (is_array($decoded)) {
                $customPermissions = $decoded;
            }
        }

        if (!is_array($customPermissions)) {
            return $rolePermissions;
        }

        // Deep merge - custom permissions override role defaults
        return array_replace_recursive($rolePermissions, $customPermissions);
    }
}
