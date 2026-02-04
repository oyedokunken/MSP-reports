<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\UserProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserProvisioningService $provisioningService;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisioningService = new UserProvisioningService();
        
        // Create a test company
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
        ]);
    }

    public function test_provision_user_creates_new_user_with_viewer_role()
    {
        $email = 'newuser@example.com';
        $name = 'New User';
        $authMethod = 'saml';

        $user = $this->provisioningService->provisionUser(
            $this->company->company_id,
            $email,
            $name,
            $authMethod
        );

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($email, $user->email);
        $this->assertEquals($name, $user->name);
        $this->assertEquals($authMethod, $user->auth_method->value);
        $this->assertEquals('active', $user->status->value);
        $this->assertNotNull($user->email_verified_at);
        
        // Check that Viewer role was assigned
        $this->assertEquals('Viewer', $user->role->name);
        
        // Check default permissions
        $this->assertTrue($user->permissions['integrations']['view']);
        $this->assertFalse($user->permissions['integrations']['manage']);
        $this->assertTrue($user->permissions['reports']['view']);
        $this->assertFalse($user->permissions['reports']['configure']);
    }

    public function test_provision_user_creates_viewer_role_if_not_exists()
    {
        // Ensure Viewer role doesn't exist
        Role::where('name', 'Viewer')->delete();

        $user = $this->provisioningService->provisionUser(
            $this->company->company_id,
            'test@example.com',
            'Test User',
            'ldap'
        );

        $viewerRole = Role::where('name', 'Viewer')->first();
        $this->assertNotNull($viewerRole);
        $this->assertEquals($viewerRole->id, $user->role_id);
    }

    public function test_provision_user_with_scoped_customers()
    {
        $user = $this->provisioningService->provisionUser(
            $this->company->company_id,
            'scoped@example.com',
            'Scoped User',
            'm365',
            ['scoped_customers' => [1, 2, 3]]
        );

        $this->assertEquals([1, 2, 3], $user->scoped_customers);
    }

    public function test_provision_user_logs_audit_event()
    {
        $email = 'audit@example.com';
        
        $user = $this->provisioningService->provisionUser(
            $this->company->company_id,
            $email,
            'Audit User',
            'saml'
        );

        $auditLog = AuditLog::where('event_type', 'user_jit_provisioned')
            ->where('target_id', $user->user_id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('System', $auditLog->actor_type);
        $this->assertEquals('User', $auditLog->target_type);
    }

    public function test_find_or_provision_user_returns_existing_user()
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'company_id' => $this->company->company_id,
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        $user = $this->provisioningService->findOrProvisionUser(
            $this->company->company_id,
            'existing@example.com',
            'Different Name',
            'saml'
        );

        $this->assertEquals($existingUser->user_id, $user->user_id);
        $this->assertEquals('Existing User', $user->name); // Name should not change
    }

    public function test_find_or_provision_user_creates_new_user_if_not_exists()
    {
        $initialCount = User::count();

        $user = $this->provisioningService->findOrProvisionUser(
            $this->company->company_id,
            'newuser@example.com',
            'New User',
            'ldap'
        );

        $this->assertEquals($initialCount + 1, User::count());
        $this->assertEquals('newuser@example.com', $user->email);
    }

    public function test_find_or_provision_user_updates_last_login()
    {
        $existingUser = User::factory()->create([
            'company_id' => $this->company->company_id,
            'email' => 'login@example.com',
            'last_login_at' => now()->subDays(7),
        ]);

        $oldLoginTime = $existingUser->last_login_at;

        $user = $this->provisioningService->findOrProvisionUser(
            $this->company->company_id,
            'login@example.com',
            'Login User',
            'saml'
        );

        $this->assertTrue($user->last_login_at->greaterThan($oldLoginTime));
    }

    public function test_update_user_from_provider_updates_name()
    {
        $user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'name' => 'Old Name',
        ]);

        $this->provisioningService->updateUserFromProvider($user, [
            'name' => 'New Name',
        ]);

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
    }

    public function test_update_user_from_provider_updates_scoped_customers()
    {
        $user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'scoped_customers' => [1, 2],
        ]);

        $this->provisioningService->updateUserFromProvider($user, [
            'scoped_customers' => [3, 4, 5],
        ]);

        $user->refresh();
        $this->assertEquals([3, 4, 5], $user->scoped_customers);
    }

    public function test_update_user_from_provider_logs_audit_event()
    {
        $user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'name' => 'Original Name',
        ]);

        $this->provisioningService->updateUserFromProvider($user, [
            'name' => 'Updated Name',
        ]);

        $auditLog = AuditLog::where('event_type', 'user_updated_from_provider')
            ->where('target_id', $user->user_id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('System', $auditLog->actor_type);
    }
}
