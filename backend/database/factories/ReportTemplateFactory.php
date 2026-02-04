<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'template_name' => 'Monthly Summary',
            'description' => 'Standard monthly summary report',
            'report_type' => 'autotask_summary',
            'frequency' => 'monthly',
            'schedule_config' => ['day_of_month' => 1],
            'time_of_day' => '09:00',
            'timezone' => 'UTC',
            'included_sections' => ['summary', 'tickets', 'projects'],
            'delivery_settings' => ['method' => 'email'],
            'report_options' => [],
            'active' => true,
        ];
    }
}
