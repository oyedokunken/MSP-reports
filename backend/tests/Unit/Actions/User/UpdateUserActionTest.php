<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\UpdateUserAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateUserActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateUserAction();
    }

    public function test_it_updates_a_user_successfully(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);
        $user = User::factory()->create(['company_id' => $company->company_id, 'name' => 'Old Name', 'role_id' => $role->id]);

        $data = [
            'name' => 'New Name',
            'role_id' => $role->id,
        ];

        $updatedUser = $this->action->execute($authUser, $user->user_id, $data);

        $this->assertEquals('New Name', $updatedUser->name);
        $this->assertEquals($role->id, $updatedUser->role_id);
    }

    public function test_it_throws_exception_if_user_not_found_in_company(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company1->company_id, 'role_id' => $role->id]);
        $userInOtherCompany = User::factory()->create(['company_id' => $company2->company_id, 'role_id' => $role->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('User not found in your company.');

        $this->action->execute($authUser, $userInOtherCompany->user_id, ['name' => 'New Name']);
    }
}
