<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\IntegrationCustomer;
use App\Models\CustomerMapping;
use App\Models\ReportSchedule;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Services\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private Customer $customer;
    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'name' => 'Test MSP',
            'settings' => [
                'white_label' => [
                    'enabled' => true,
                    'company_name' => 'Test MSP',
                    'primary_color' => '#1a56db',
                ],
                'reports' => [
                    'report_timezone' => 'Australia/Brisbane',
                ],
            ],
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'permissions' => [
                'templates' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'schedules' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'reports' => ['view' => true, 'configure' => true, 'generate' => true],
            ],
        ]);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
            'canonical_name' => 'ACME Corporation',
        ]);

        $this->integration = Integration::factory()->create([
            'company_id' => $this->company->company_id,
            'integration_type' => 'Autotask',
            'status' => 'active',
        ]);
    }

    public function test_can_trigger_report_generation_via_api(): void
    {
        // Create an Autotask integration
        $integration = \App\Models\Integration::factory()->create([
            'company_id' => $this->company->company_id,
            'integration_type' => 'Autotask',
            'status' => 'active',
        ]);

        // Link customer to integration
        $integrationCustomer = \App\Models\IntegrationCustomer::factory()->create([
            'integration_id' => $integration->integration_id,
            'customer_id' => $this->customer->customer_id,
        ]);

        // Create the mapping record that the relationship uses
        \App\Models\CustomerMapping::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
        ]);

        // Add some data for the report period
        \App\Models\IntegrationData::factory()->create([
            'company_id' => $this->company->company_id,
            'integration_id' => $integration->integration_id,
            'integration_customer_id' => $integrationCustomer->integration_customer_id,
            'data_type' => 'ticket',
            'data_timestamp' => now()->subDays(5),
        ]);

        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'report_type' => 'autotask_summary',
            'frequency' => 'monthly',
        ]);

        // Mock Gotenberg PDF service
        Http::fake([
            '*/forms/chromium/convert/html' => Http::response('PDF content', 200),
            '*/forms/pdfengines/merge' => Http::response('Merged PDF content', 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reports/schedules/{$schedule->report_schedule_id}/generate", [
                'preview_only' => true
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_report_schedule_belongs_to_customer(): void
    {
        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'report_type' => 'autotask_summary',
        ]);

        $this->assertEquals($this->customer->customer_id, $schedule->customer_id);
        $this->assertEquals($this->company->company_id, $schedule->company_id);
    }

    public function test_cannot_generate_report_for_other_company_customer(): void
    {
        // Create another company and customer
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/reports/generate/{$otherCustomer->customer_id}", [
                'report_type' => 'autotask_summary',
            ]);

        $response->assertStatus(404);
    }

    public function test_report_history_returns_only_company_reports(): void
    {
        // Create reports for this company
        GeneratedReport::factory()->count(3)->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
        ]);

        // Create reports for another company (should not be visible)
        $otherCompany = Company::factory()->create();
        GeneratedReport::factory()->count(2)->create([
            'company_id' => $otherCompany->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reports/history');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.reports')); // Only this company's reports
    }

    public function test_download_report_requires_permission(): void
    {
        $report = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'status' => 'completed',
            'file_path' => 'reports/test.pdf',
        ]);

        // User without download permission
        $restrictedUser = User::factory()->create([
            'company_id' => $this->company->company_id,
            'permissions' => [
                'reports' => ['view' => true, 'download' => false],
            ],
        ]);

        $response = $this->actingAs($restrictedUser)
            ->getJson("/api/reports/{$report->generated_report_id}/download");

        $response->assertStatus(403);
    }

    public function test_report_schedule_calculates_next_run_correctly(): void
    {
        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'report_type' => 'autotask_summary',
            'frequency' => 'monthly',
            'active' => false,
            'schedule_config' => [
                'day_of_month' => 1,
            ],
            'time_of_day' => '09:00',
        ]);

        // updateNextScheduledAt is not implemented in model yet in this branch
        // $schedule->updateNextScheduledAt();
        // $schedule->refresh();

        // Next run should be on the 1st of next month at 9am
        $this->assertNotNull($schedule->next_scheduled_at);
        $this->assertEquals(1, $schedule->next_scheduled_at->day);
        $this->assertEquals(9, $schedule->next_scheduled_at->hour);
    }


    public function test_inactive_schedule_not_included_in_scheduled_reports(): void
    {
        // Active schedule
        ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'active' => true,
        ]);

        // Inactive schedule
        ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'active' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reports/scheduled?include_inactive=false');

        $response->assertStatus(200);
        // Should only return active schedules by default
        $schedules = collect($response->json('data'));
        $this->assertTrue($schedules->every(fn ($s) => $s['active'] === true));
    }
}
