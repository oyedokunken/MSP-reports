<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResetPasswordActionTest extends TestCase
{
    use RefreshDatabase;

    private ResetPasswordAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ResetPasswordAction();
    }

    public function test_it_resets_password_successfully(): void
    {
        $role = \App\Models\Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->company_id,
            'role_id' => $role->id,
            'email' => 'test@example.com',
            'auth_method' => 'local',
            'password' => Hash::make('old_password'),
        ]);

        $token = 'secret_token';
        PasswordResetToken::create([
            'email' => 'test@example.com',
            'token' => hash('sha256', $token),
            'expires_at' => now()->addHour(),
        ]);

        $data = [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'new_password_123',
        ];

        $this->action->execute($data, '127.0.0.1', 'Mozilla');

        $user->refresh();
        $this->assertTrue(Hash::check('new_password_123', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'test@example.com']);
        
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_reset_completed',
            'actor_id' => $user->user_id,
        ]);
    }

    public function test_it_throws_exception_for_invalid_token(): void
    {
        $data = [
            'email' => 'test@example.com',
            'token' => 'invalid_token',
            'password' => 'new_password_123',
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid or expired password reset token.');

        $this->action->execute($data, null, null);
    }
}
