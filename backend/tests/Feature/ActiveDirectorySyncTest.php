<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\ActiveDirectoryUser;
use App\Models\ActiveDirectoryGroup;
use App\Models\ActiveDirectoryLicenseAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveDirectorySyncTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Customer $customer;
    private Integration $integration;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);
        $this->integration = Integration::factory()->create([
            'company_id' => $this->company->company_id,
            'integration_type' => 'active_directory',
            'status' => 'active',
        ]);

        // Create API key for agent authentication
        $plainKey = 'msp_test_key_' . bin2hex(random_bytes(24));
        $this->apiKey = $plainKey;

        ApiKey::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'key_hash' => hash('sha256', $plainKey),
            'permissions' => ['integrations' => ['sync' => true]],
            'revoked' => false,
        ]);
    }

    public function test_can_push_ad_sync_data(): void
    {
        $payload = [
            'syncType' => 'full',
            'syncTimestamp' => now()->toIso8601String(),
            'domain' => 'test.local',
            'data' => [
                'users' => [
                    [
                        'samAccountName' => 'jdoe',
                        'userPrincipalName' => 'jdoe@test.local',
                        'displayName' => 'John Doe',
                        'email' => 'jdoe@test.local',
                        'enabled' => true,
                        'distinguishedName' => 'CN=John Doe,OU=Users,DC=test,DC=local',
                    ],
                ],
                'securityGroups' => [
                    [
                        'name' => 'Domain Users',
                        'samAccountName' => 'Domain Users',
                        'distinguishedName' => 'CN=Domain Users,OU=Groups,DC=test,DC=local',
                        'groupCategory' => 'Security',
                        'groupScope' => 'Global',
                    ],
                ],
                'groupMemberships' => [
                    [
                        'userSamAccountName' => 'jdoe',
                        'userDN' => 'CN=John Doe,OU=Users,DC=test,DC=local',
                        'groupDN' => 'CN=Domain Users,OU=Groups,DC=test,DC=local',
                    ],
                ],
                'licenseAssignments' => [
                    [
                        'userSamAccountName' => 'jdoe',
                        'userDN' => 'CN=John Doe,OU=Users,DC=test,DC=local',
                        'licenseName' => 'Microsoft 365',
                        'licenseType' => 'SPLA',
                        'costPerUser' => 12.50,
                        'assignedViaGroup' => 'Office365-Users',
                        'assignedViaGroupDN' => 'CN=Office365-Users,OU=Groups,DC=test,DC=local',
                    ],
                ],
            ],
            'statistics' => [
                'userCount' => 1,
                'groupCount' => 1,
                'membershipCount' => 1,
                'licenseAssignmentCount' => 1,
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->postJson('/api/integrations/active-directory/push', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify data was stored
        $this->assertDatabaseHas('active_directory_users', [
            'sam_account_name' => 'jdoe',
            'company_id' => $this->company->company_id,
        ]);

        $this->assertDatabaseHas('active_directory_groups', [
            'name' => 'Domain Users',
            'company_id' => $this->company->company_id,
        ]);

        $this->assertDatabaseHas('active_directory_license_assignments', [
            'user_sam_account_name' => 'jdoe',
            'license_name' => 'Microsoft 365',
            'license_type' => 'SPLA',
        ]);
    }

    public function test_rejects_request_without_api_key(): void
    {
        $response = $this->postJson('/api/integrations/active-directory/push', [
            'syncType' => 'full',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_revoked_api_key(): void
    {
        // Revoke the key
        ApiKey::where('key_hash', hash('sha256', $this->apiKey))->update(['revoked' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->postJson('/api/integrations/active-directory/push', [
            'syncType' => 'full',
            'data' => [],
        ]);

        $response->assertStatus(401);
    }

    public function test_full_sync_clears_old_license_assignments(): void
    {
        // Create existing license assignment
        ActiveDirectoryLicenseAssignment::create([
            'company_id' => $this->company->company_id,
            'integration_id' => $this->integration->integration_id,
            'customer_id' => $this->customer->customer_id,
            'user_sam_account_name' => 'olduser',
            'license_name' => 'Old License',
            'license_type' => 'SPLA',
            'cost_per_user' => 10.00,
            'assigned_via_group' => 'Old Group',
        ]);

        $payload = [
            'syncType' => 'full',
            'syncTimestamp' => now()->toIso8601String(),
            'domain' => 'test.local',
            'data' => [
                'users' => [],
                'securityGroups' => [],
                'groupMemberships' => [],
                'licenseAssignments' => [
                    [
                        'userSamAccountName' => 'newuser',
                        'licenseName' => 'New License',
                        'licenseType' => 'SPLA',
                        'costPerUser' => 15.00,
                        'assignedViaGroup' => 'New Group',
                    ],
                ],
            ],
            'statistics' => [
                'userCount' => 0,
                'groupCount' => 0,
                'membershipCount' => 0,
                'licenseAssignmentCount' => 1,
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->postJson('/api/integrations/active-directory/push', $payload);

        $response->assertStatus(200);

        // Old assignment should be gone
        $this->assertDatabaseMissing('active_directory_license_assignments', [
            'user_sam_account_name' => 'olduser',
        ]);

        // New assignment should exist
        $this->assertDatabaseHas('active_directory_license_assignments', [
            'user_sam_account_name' => 'newuser',
            'license_name' => 'New License',
        ]);
    }
}
