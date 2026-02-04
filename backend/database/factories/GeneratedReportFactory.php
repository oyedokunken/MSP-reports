<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class GeneratedReportFactory extends Factory
{
    protected $model = GeneratedReport::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'report_schedule_id' => ReportSchedule::factory(),
            'generated_at' => now(),
            'report_period' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'status' => 'completed',
            'file_path' => 'reports/test.pdf',
            'file_size' => 1024,
            'delivered' => false,
            'delivered_at' => null,
            'delivery_method' => 'email',
            'error_message' => null,
            'retention_until' => now()->addMonths(3),
        ];
    }
}
