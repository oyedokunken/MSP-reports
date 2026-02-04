<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'permissions' => [
                'api_keys' => ['view' => true, 'create' => true, 'revoke' => true],
            ],
        ]);
    }

    public function test_can_list_api_keys(): void
    {
        ApiKey::factory()->count(3)->create([
            'company_id' => $this->company->company_id,
            'created_by_user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/api-keys');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'api_key_id',
                        'display_name',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_can_create_api_key(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/api-keys', [
                'display_name' => 'Test API Key',
                'permissions' => [
                    'integrations' => ['view' => true, 'sync' => true],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'api_key',
                'data' => [
                    'api_key_id',
                    'display_name',
                ],
            ]);

        // Verify key starts with msp_
        $this->assertStringStartsWith('msp_', $response->json('api_key'));

        // Verify stored in database
        $this->assertDatabaseHas('api_keys', [
            'display_name' => 'Test API Key',
            'company_id' => $this->company->company_id,
        ]);
    }

    public function test_can_create_customer_scoped_api_key(): void
    {
        $customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/api-keys', [
                'display_name' => 'Customer Agent Key',
                'customer_id' => $customer->customer_id,
                'permissions' => [
                    'integrations' => ['sync' => true],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('api_keys', [
            'display_name' => 'Customer Agent Key',
            'customer_id' => $customer->customer_id,
        ]);
    }

    public function test_can_revoke_api_key(): void
    {
        $apiKey = ApiKey::factory()->create([
            'company_id' => $this->company->company_id,
            'created_by_user_id' => $this->user->user_id,
            'revoked' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/api-keys/{$apiKey->api_key_id}/revoke");

        $response->assertStatus(200);

        $this->assertDatabaseHas('api_keys', [
            'api_key_id' => $apiKey->api_key_id,
            'revoked' => true,
        ]);
    }

    public function test_cannot_access_other_company_api_keys(): void
    {
        $otherCompany = Company::factory()->create();
        $otherApiKey = ApiKey::factory()->create([
            'company_id' => $otherCompany->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/api-keys/{$otherApiKey->api_key_id}/revoke");

        $response->assertStatus(403);
    }

    public function test_api_key_requires_display_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/api-keys', [
                'permissions' => ['integrations' => ['view' => true]],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['display_name']);
    }

    public function test_ip_whitelist_validates_ip_format(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/api-keys', [
                'display_name' => 'Test Key',
                'ip_whitelist' => ['not-an-ip', '192.168.1.1'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ip_whitelist.0']);
    }
}
