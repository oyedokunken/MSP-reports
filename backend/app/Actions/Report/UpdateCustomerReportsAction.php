<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\Company;
use App\Models\Customer;
use App\Models\ReportSchedule;

final class UpdateCustomerReportsAction
{
    /**
     * @param array<int, array<string, mixed>> $schedulesData
     * @return array{schedules_created: int, schedules_updated: int}
     */
    public function execute(Company $company, Customer $customer, array $schedulesData): array
    {
        $schedulesCreated = 0;
        $schedulesUpdated = 0;

        foreach ($schedulesData as $scheduleData) {
            $existingSchedule = ReportSchedule::where('customer_id', $customer->customer_id)
                ->where('company_id', $company->company_id)
                ->where('report_type', $scheduleData['report_type'])
                ->first();

            if ($existingSchedule) {
                $existingSchedule->update([
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                ]);
                $schedulesUpdated++;
            } else {
                ReportSchedule::create([
                    'company_id' => $company->company_id,
                    'customer_id' => $customer->customer_id,
                    'report_type' => $scheduleData['report_type'],
                    'frequency' => $scheduleData['frequency'],
                    'time_of_day' => $scheduleData['time_of_day'] ?? '09:00',
                    'schedule_config' => $scheduleData['schedule_config'] ?? null,
                    'active' => $scheduleData['active'] ?? true,
                    'timezone' => $company->settings['reports']['report_timezone'] ?? 'UTC',
                ]);
                $schedulesCreated++;
            }
        }

        return [
            'schedules_created' => $schedulesCreated,
            'schedules_updated' => $schedulesUpdated
        ];
    }
}
