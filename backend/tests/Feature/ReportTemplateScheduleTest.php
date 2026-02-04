<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTemplateScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->company_id,
            'permissions' => [
                'templates' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'schedules' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
                'reports' => ['view' => true, 'configure' => true],
            ],
        ]);
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);
    }

    public function test_template_update_syncs_to_non_customized_schedules(): void
    {
        $template = ReportTemplate::factory()->create([
            'company_id' => $this->company->company_id,
            'template_name' => 'Monthly Report',
            'report_type' => 'monthly_summary',
            'frequency' => 'monthly',
            'time_of_day' => '09:00',
        ]);

        // Create schedule linked to template, not customized
        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'template_id' => $template->template_id,
            'report_type' => 'monthly_summary',
            'frequency' => 'monthly',
            'time_of_day' => '09:00',
            'is_customized' => false,
        ]);

        // Update template
        $response = $this->actingAs($this->user)
            ->putJson("/api/report-templates/{$template->template_id}", [
                'template_name' => 'Monthly Report Updated',
                'time_of_day' => '10:00',
            ]);

        $response->assertStatus(200);

        // Schedule should be updated
        $schedule->refresh();
        $this->assertEquals('10:00', $schedule->time_of_day);
    }

    public function test_template_update_skips_customized_schedules(): void
    {
        $template = ReportTemplate::factory()->create([
            'company_id' => $this->company->company_id,
            'template_name' => 'Monthly Report',
            'report_type' => 'monthly_summary',
            'frequency' => 'monthly',
            'time_of_day' => '09:00',
        ]);

        // Create customized schedule
        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'template_id' => $template->template_id,
            'report_type' => 'monthly_summary',
            'frequency' => 'monthly',
            'time_of_day' => '08:00', // Different from template
            'is_customized' => true,
        ]);

        // Update template
        $response = $this->actingAs($this->user)
            ->putJson("/api/report-templates/{$template->template_id}", [
                'template_name' => 'Monthly Report Updated',
                'time_of_day' => '10:00',
            ]);

        $response->assertStatus(200);

        // Schedule should NOT be updated (customized)
        $schedule->refresh();
        $this->assertEquals('08:00', $schedule->time_of_day);
    }

    public function test_schedule_marked_customized_when_edited(): void
    {
        $template = ReportTemplate::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'template_id' => $template->template_id,
            'is_customized' => false,
        ]);

        // Edit the schedule directly
        $response = $this->actingAs($this->user)
            ->patchJson("/api/reports/schedules/{$schedule->report_schedule_id}", [
                'time_of_day' => '14:00',
            ]);

        $response->assertStatus(200);

        $schedule->refresh();
        $this->assertTrue($schedule->is_customized);
    }

}
