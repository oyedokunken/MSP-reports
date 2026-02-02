<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Enums\AuthMethod;
use Tests\TestCase;

class ChangePasswordActionTest extends TestCase
{
    use RefreshDatabase;

    private ChangePasswordAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ChangePasswordAction();
    }

    public function test_it_changes_password_successfully(): void
    {
        $role = \App\Models\Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->company_id,
            'role_id' => $role->id,
            'auth_method' => AuthMethod::LOCAL,
            'password' => Hash::make('old_password'),
        ]);

        $data = [
            'current_password' => 'old_password',
            'new_password' => 'new_password_123',
        ];

        $this->action->execute($user, $data, '127.0.0.1', 'Mozilla');

        $user->refresh();
        $this->assertTrue(Hash::check('new_password_123', $user->password));
        
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_changed',
            'actor_id' => $user->user_id,
        ]);
    }

    public function test_it_throws_exception_if_current_password_incorrect(): void
    {
        $role = \App\Models\Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->company_id,
            'role_id' => $role->id,
            'auth_method' => AuthMethod::LOCAL,
            'password' => Hash::make('old_password'),
        ]);

        $data = [
            'current_password' => 'wrong_password',
            'new_password' => 'new_password_123',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Current password is incorrect.');

        $this->action->execute($user, $data, null, null);
    }
}
