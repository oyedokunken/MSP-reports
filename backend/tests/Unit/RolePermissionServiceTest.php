<?php

namespace Tests\Unit;

use App\Services\RolePermissionService;
use PHPUnit\Framework\TestCase;

class RolePermissionServiceTest extends TestCase
{
    public function test_admin_has_all_permissions(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('admin');

        $this->assertTrue($permissions['integrations']['view']);
        $this->assertTrue($permissions['integrations']['manage']);
        $this->assertTrue($permissions['integrations']['sync']);
        $this->assertTrue($permissions['users']['view']);
        $this->assertTrue($permissions['users']['manage']);
        $this->assertTrue($permissions['company_settings']['edit']);
        $this->assertTrue($permissions['security']['configure']);
    }

    public function test_viewer_has_minimal_permissions(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('viewer');

        $this->assertFalse($permissions['integrations']['view']);
        $this->assertFalse($permissions['integrations']['manage']);
        $this->assertTrue($permissions['reports']['view']);
        $this->assertTrue($permissions['reports']['download']);
        $this->assertFalse($permissions['reports']['configure']);
        $this->assertFalse($permissions['users']['view']);
        $this->assertFalse($permissions['users']['manage']);
    }

    public function test_customer_role_is_most_restrictive(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('customer');

        $this->assertFalse($permissions['integrations']['view']);
        $this->assertFalse($permissions['customers']['view']);
        $this->assertTrue($permissions['reports']['view']);
        $this->assertTrue($permissions['reports']['download']);
        $this->assertFalse($permissions['schedules']['view']);
        $this->assertFalse($permissions['users']['view']);
    }

    public function test_technician_can_manage_integrations(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('technician');

        $this->assertTrue($permissions['integrations']['view']);
        $this->assertTrue($permissions['integrations']['manage']);
        $this->assertTrue($permissions['integrations']['sync']);
        $this->assertTrue($permissions['exchange']['view']);
        $this->assertTrue($permissions['exchange']['manage']);
    }

    public function test_manager_can_configure_reports(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('manager');

        $this->assertTrue($permissions['reports']['view']);
        $this->assertTrue($permissions['reports']['configure']);
        $this->assertTrue($permissions['reports']['generate']);
        $this->assertTrue($permissions['schedules']['create']);
        $this->assertFalse($permissions['integrations']['manage']);
    }

    public function test_unknown_role_defaults_to_viewer(): void
    {
        $permissions = RolePermissionService::getDefaultPermissions('unknown_role');
        $viewerPermissions = RolePermissionService::getDefaultPermissions('viewer');

        $this->assertEquals($viewerPermissions, $permissions);
    }

    public function test_has_permission_returns_correct_value(): void
    {
        $permissions = [
            'reports' => ['view' => true, 'configure' => false],
        ];

        $this->assertTrue(RolePermissionService::hasPermission($permissions, 'reports', 'view'));
        $this->assertFalse(RolePermissionService::hasPermission($permissions, 'reports', 'configure'));
        $this->assertFalse(RolePermissionService::hasPermission($permissions, 'reports', 'nonexistent'));
        $this->assertFalse(RolePermissionService::hasPermission($permissions, 'nonexistent', 'view'));
    }

    public function test_has_permission_handles_null(): void
    {
        $this->assertFalse(RolePermissionService::hasPermission(null, 'reports', 'view'));
    }

    public function test_merge_permissions_overrides_role_defaults(): void
    {
        $customPermissions = [
            'integrations' => ['manage' => true],
        ];

        $merged = RolePermissionService::mergePermissions('viewer', $customPermissions);

        // Custom permission overrides
        $this->assertTrue($merged['integrations']['manage']);
        // Other permissions from viewer role remain
        $this->assertFalse($merged['integrations']['view']);
        $this->assertTrue($merged['reports']['view']);
    }

    public function test_merge_permissions_with_null_returns_role_defaults(): void
    {
        $merged = RolePermissionService::mergePermissions('admin', null);
        $adminPermissions = RolePermissionService::getDefaultPermissions('admin');

        $this->assertEquals($adminPermissions, $merged);
    }

    public function test_all_roles_have_complete_permission_structure(): void
    {
        $roles = ['admin', 'manager', 'technician', 'viewer', 'customer'];
        $expectedResources = [
            'integrations', 'customers', 'reports', 'schedules', 'templates',
            'users', 'api_keys', 'licenses', 'monitoring', 'notifications',
            'security', 'exchange', 'company_settings',
        ];

        foreach ($roles as $role) {
            $permissions = RolePermissionService::getDefaultPermissions($role);

            foreach ($expectedResources as $resource) {
                $this->assertArrayHasKey(
                    $resource,
                    $permissions,
                    "Role '{$role}' missing resource '{$resource}'"
                );
            }
        }
    }
}
