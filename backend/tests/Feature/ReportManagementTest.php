<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportManagementTest extends TestCase
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
                'reports' => ['view' => true, 'download' => true, 'configure' => true],
                'schedules' => ['edit' => true, 'delete' => true],
            ],
        ]);
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->company_id,
        ]);
    }

    public function test_can_delete_report_schedule_and_template(): void
    {
        $template = ReportTemplate::factory()->create([
            'company_id' => $this->company->company_id,
        ]);

        $schedule = ReportSchedule::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'template_id' => $template->template_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reports/schedules/{$schedule->report_schedule_id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('report_schedules', ['report_schedule_id' => $schedule->report_schedule_id]);
        $this->assertDatabaseMissing('report_templates', ['template_id' => $template->template_id]);
    }

    public function test_can_delete_generated_report_and_file(): void
    {
        Storage::fake('reports');
        $filePath = 'reports/test_report.pdf';
        Storage::disk('reports')->put($filePath, 'dummy content');

        $report = GeneratedReport::factory()->create([
            'company_id' => $this->company->company_id,
            'customer_id' => $this->customer->customer_id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reports/{$report->generated_report_id}");

        $response->assertStatus(200);
        
        $this->assertSoftDeleted('generated_reports', ['generated_report_id' => $report->generated_report_id]);
        Storage::disk('reports')->assertMissing($filePath);
    }

    public function test_cannot_delete_other_company_schedule(): void
    {
        $otherCompany = Company::factory()->create();
        $schedule = ReportSchedule::factory()->create([
            'company_id' => $otherCompany->company_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reports/schedules/{$schedule->report_schedule_id}");

        $response->assertStatus(404);
    }
}
