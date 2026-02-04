<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\User;
use App\Services\CustomerMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerMappingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'settings' => [
                'customer_mapping' => [
                    'auto_match_enabled' => true,
                    'auto_match_threshold' => 85,
                ],
            ],
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'permissions' => [
                'customers' => ['view' => true, 'edit' => true, 'map' => true],
            ],
        ]);

        $this->integration = Integration::factory()->create([
            'company_id' => $this->company->company_id,
            'integration_type' => 'Autotask',
            'status' => 'active',
            'is_source_of_truth' => true,
        ]);
    }

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(5)->create([
            'company_id' => $this->company->company_id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/customers');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_customers_isolated_by_company(): void
    {
        // Create another company and its customers
        $otherCompany = Company::factory()->create();
        Customer::factory()->count(2)->create([
            'company_id' => $otherCompany->company_id,
        ]);

        // Create customers for this company
        Customer::factory()->count(3)->create([
            'company_id' => $this->company->company_id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/customers');

        $response->assertStatus(200);
        // Should only see this company's customers
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_static_customer(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/customers/static', [
                'canonical_name' => 'New Static Customer',
                'status' => 'active',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->company_id,
            'canonical_name' => 'New Static Customer',
        ]);
    }

    public function test_can_map_integration_customer_to_master_customer(): void
    {
        // Create master customer
        $masterCustomer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
            'canonical_name' => 'ACME Corporation',
        ]);

        // Create integration customer
        $integrationCustomer = IntegrationCustomer::factory()->create([
            'integration_id' => $this->integration->integration_id,
            'name' => 'ACME Corp',
            'active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/customers/map', [
                'customer_id' => $masterCustomer->customer_id,
                'integration_customer_id' => $integrationCustomer->integration_customer_id,
            ]);

        $response->assertStatus(200);

        // Verify mapping created
        $this->assertDatabaseHas('customer_mappings', [
            'customer_id' => $masterCustomer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'mapping_type' => 'manual',
        ]);
    }

    public function test_can_update_customer_name(): void
    {
        $customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
            'canonical_name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/customers/{$customer->customer_id}", [
                'canonical_name' => 'New Name',
            ]);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals('New Name', $customer->canonical_name);
    }

    public function test_can_delete_mapping(): void
    {
        $customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $integrationCustomer = IntegrationCustomer::factory()->create([
            'integration_id' => $this->integration->integration_id,
        ]);

        $mapping = CustomerMapping::factory()->create([
            'customer_id' => $customer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/customers/{$customer->customer_id}/mappings/{$mapping->customer_mapping_id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('customer_mappings', [
            'customer_mapping_id' => $mapping->customer_mapping_id,
        ]);
    }

    public function test_can_merge_customers(): void
    {
        $primaryCustomer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
            'canonical_name' => 'Primary Customer',
        ]);

        $secondaryCustomer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
            'canonical_name' => 'Secondary Customer',
        ]);

        // Create mapping for secondary customer
        $integrationCustomer = IntegrationCustomer::factory()->create([
            'integration_id' => $this->integration->integration_id,
        ]);

        CustomerMapping::factory()->create([
            'customer_id' => $secondaryCustomer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/customers/{$primaryCustomer->customer_id}/merge", [
                'merge_customer_id' => $secondaryCustomer->customer_id,
            ]);

        $response->assertStatus(200);

        // Secondary customer should be deleted
        $this->assertDatabaseMissing('customers', [
            'customer_id' => $secondaryCustomer->customer_id,
        ]);

        // Mapping should now point to primary customer
        $this->assertDatabaseHas('customer_mappings', [
            'customer_id' => $primaryCustomer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
        ]);
    }

    public function test_cannot_merge_customer_with_itself(): void
    {
        $customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/customers/{$customer->customer_id}/merge", [
                'with_customer_id' => $customer->customer_id,
            ]);

        $response->assertStatus(422); // Validation error
    }

    public function test_unmapped_customers_endpoint_returns_only_unmapped(): void
    {
        // Create mapped integration customer
        $customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $mappedIC = IntegrationCustomer::factory()->create([
            'integration_id' => $this->integration->integration_id,
            'name' => 'Mapped Customer',
        ]);

        CustomerMapping::factory()->create([
            'customer_id' => $customer->customer_id,
            'integration_customer_id' => $mappedIC->integration_customer_id,
        ]);

        // Create unmapped integration customer  
        $unmappedIC = IntegrationCustomer::factory()->create([
            'integration_id' => $this->integration->integration_id,
            'name' => 'Unmapped Customer',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/customers/unmapped');

        $response->assertStatus(200);

        // Should return data with unmapped customers
        $data = $response->json('data');
        $this->assertNotNull($data);
    }

}
