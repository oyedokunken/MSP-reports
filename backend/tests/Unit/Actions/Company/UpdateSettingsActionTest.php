<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Company;

use App\Actions\Company\UpdateSettingsAction;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateSettingsActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateSettingsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateSettingsAction();
    }

    public function test_it_updates_company_settings(): void
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create([
            'name' => 'Old Name',
        ]);
        $user = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);

        $data = [
            'name' => 'New Name',
            'contact_email' => 'contact@example.com',
        ];

        $this->action->execute($company, $data, $user->user_id);

        $company->refresh();
        $this->assertEquals('New Name', $company->name);
        $this->assertEquals('contact@example.com', $company->contact_email);
    }
}
