<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Report;

use App\Actions\Report\UpdateCustomerReportsAction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCustomerReportsActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdateCustomerReportsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateCustomerReportsAction();
    }

    public function test_it_creates_new_schedules_for_customer(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->company_id]);

        $schedulesData = [
            [
                'report_type' => 'm365_license_usage',
                'frequency' => 'monthly',
                'time_of_day' => '10:00',
                'active' => true,
            ],
            [
                'report_type' => 'security_summary',
                'frequency' => 'weekly',
            ]
        ];

        $result = $this->action->execute($company, $customer, $schedulesData);

        $this->assertEquals(2, $result['schedules_created']);
        $this->assertEquals(0, $result['schedules_updated']);
        
        $this->assertDatabaseHas('report_schedules', [
            'customer_id' => $customer->customer_id,
            'report_type' => 'm365_license_usage',
            'frequency' => 'monthly',
        ]);
    }

    public function test_it_updates_existing_schedules(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->company_id]);
        
        ReportSchedule::create([
            'company_id' => $company->company_id,
            'customer_id' => $customer->customer_id,
            'report_type' => 'm365_license_usage',
            'frequency' => 'daily',
            'active' => true,
            'timezone' => 'UTC',
        ]);

        $schedulesData = [
            [
                'report_type' => 'm365_license_usage',
                'frequency' => 'monthly',
                'active' => false,
            ]
        ];

        $result = $this->action->execute($company, $customer, $schedulesData);

        $this->assertEquals(0, $result['schedules_created']);
        $this->assertEquals(1, $result['schedules_updated']);
        
        $this->assertDatabaseHas('report_schedules', [
            'customer_id' => $customer->customer_id,
            'report_type' => 'm365_license_usage',
            'frequency' => 'monthly',
            'active' => false,
        ]);
    }
}
