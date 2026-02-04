<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLogControllerTest extends TestCase
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
        ]);
        
        // Create some audit logs
        AuditLog::create([
            'company_id' => $this->company->company_id,
            'event_type' => 'user_login',
            'actor_type' => 'User',
            'actor_id' => $this->user->user_id,
            'target_type' => 'User',
            'target_id' => $this->user->user_id,
            'event_data' => ['email' => $this->user->email],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);
    }

    public function test_can_list_audit_logs(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'audit_log_id',
                        'event_type',
                        'actor_name',
                    ]
                ],
                'pagination'
            ]);
    }

    public function test_can_get_audit_log_stats(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/audit-logs/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_logs',
                    'logs_today',
                    'unique_actors',
                    'critical_events',
                ]
            ]);
    }

    public function test_can_export_audit_logs(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/api/audit-logs/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $this->assertStringContainsString('attachment; filename="audit_logs_', $response->baseResponse->headers->get('Content-Disposition') ?: '');
        
        $this->assertStringContainsString('"Event Type","Actor Type"', $response->getContent() ?: '');
    }
}
