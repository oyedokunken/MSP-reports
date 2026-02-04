<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;
use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'template_id' => ReportTemplate::factory(),
            'report_type' => 'autotask_summary',
            'frequency' => 'monthly',
            'schedule_config' => ['day_of_month' => 1],
            'time_of_day' => '09:00',
            'active' => true,
            'last_generated_at' => null,
            'next_scheduled_at' => now()->addMonth()->startOfMonth()->addHours(9),
            'timezone' => 'UTC',
            'is_customized' => false,
        ];
    }
}
