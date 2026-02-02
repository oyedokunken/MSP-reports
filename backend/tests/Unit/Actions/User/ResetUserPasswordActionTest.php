<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\ResetUserPasswordAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Enums\AuthMethod;
use Tests\TestCase;

class ResetUserPasswordActionTest extends TestCase
{
    use RefreshDatabase;

    private ResetUserPasswordAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResetUserPasswordAction();
    }

    public function test_it_resets_password_successfully(): void
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);
        $user = User::factory()->create([
            'company_id' => $company->company_id,
            'role_id' => $role->id,
            'auth_method' => AuthMethod::LOCAL,
            'password' => Hash::make('old_password'),
        ]);

        $this->action->execute($authUser, $user->user_id, 'new_password_123', '127.0.0.1', 'Mozilla');

        $user->refresh();
        $this->assertTrue(Hash::check('new_password_123', $user->password));
        
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->company_id,
            'event_type' => 'user_password_reset_by_admin',
            'target_id' => $user->user_id,
        ]);
    }

    public function test_it_throws_exception_for_non_local_auth_users(): void
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);
        $user = User::factory()->create([
            'company_id' => $company->company_id,
            'role_id' => $role->id,
            'auth_method' => AuthMethod::SAML,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Password reset is only available for local authentication users.');

        $this->action->execute($authUser, $user->user_id, 'new_password_123', null, null);
    }
}
