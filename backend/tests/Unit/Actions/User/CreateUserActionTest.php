<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\CreateUserAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\LicenseChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Enums\UserStatus;
use App\Enums\AuthMethod;
use Tests\TestCase;

class CreateUserActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateUserAction $action;
    private $licenseChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->licenseChecker = $this->mock(LicenseChecker::class);
        $this->notificationService = $this->mock(\App\Services\InAppNotificationService::class);
        
        // Allow notification calls
        $this->notificationService->shouldReceive('userCreated')->byDefault();

        $this->action = new CreateUserAction($this->licenseChecker, $this->notificationService);
    }

    public function test_it_creates_a_user_successfully(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);

        $this->licenseChecker->shouldReceive('canCreateUser')
            ->once()
            ->andReturn(['allowed' => true]);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
            'status' => UserStatus::ACTIVE->value,
            'auth_method' => AuthMethod::LOCAL->value,
        ];

        $user = $this->action->execute($authUser, $data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertEquals($role->id, $user->role_id);
    }

    public function test_it_throws_validation_exception_when_license_limit_reached(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);

        $this->licenseChecker->shouldReceive('canCreateUser')
            ->once()
            ->andReturn(['allowed' => false, 'reason' => 'License limit reached']);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('License limit reached');

        $this->action->execute($authUser, $data);
    }
}
