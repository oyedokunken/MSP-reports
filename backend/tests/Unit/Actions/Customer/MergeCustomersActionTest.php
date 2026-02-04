<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Customer;

use App\Actions\Customer\MergeCustomersAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Role;
use App\Models\IntegrationCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MergeCustomersActionTest extends TestCase
{
    use RefreshDatabase;

    private MergeCustomersAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MergeCustomersAction();
    }

    public function test_it_merges_two_customers_successfully(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);
        
        $primary = Customer::factory()->create(['company_id' => $company->company_id, 'canonical_name' => 'Primary']);
        $merged = Customer::factory()->create(['company_id' => $company->company_id, 'canonical_name' => 'Merged']);
        
        $integration = \App\Models\Integration::create([
            'company_id' => $company->company_id,
            'integration_type' => 'Datto',
            'display_name' => 'Test Integration',
            'status' => 'active',
        ]);

        // Add some data to merged customer
        IntegrationCustomer::create([
            'integration_id' => $integration->integration_id,
            'customer_id' => $merged->customer_id,
            'name' => 'Integration Data',
            'external_id' => 'ext_123',
            'active' => true,
        ]);

        $result = $this->action->execute($authUser, $company, $primary->customer_id, $merged->customer_id, '127.0.0.1', 'Mozilla');

        $this->assertArrayHasKey('customer', $result);
        $this->assertEquals(1, $result['integration_customers_moved']);
        
        // Check merged customer is deleted
        $this->assertDatabaseMissing('customers', ['customer_id' => $merged->customer_id]);
        
        // Check integration customer moved to primary
        $this->assertDatabaseHas('integration_customers', [
            'customer_id' => $primary->customer_id,
            'name' => 'Integration Data',
        ]);

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->company_id,
            'event_type' => 'customers_merged',
            'target_id' => $primary->customer_id,
        ]);
    }

    public function test_it_throws_exception_when_merging_with_itself(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin'], ['slug' => 'admin']);
        $company = Company::factory()->create();
        $authUser = User::factory()->create(['company_id' => $company->company_id, 'role_id' => $role->id]);
        $primary = Customer::factory()->create(['company_id' => $company->company_id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot merge a customer with itself.');

        $this->action->execute($authUser, $company, $primary->customer_id, $primary->customer_id, null, null);
    }
}
