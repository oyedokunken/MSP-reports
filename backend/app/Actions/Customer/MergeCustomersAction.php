<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerMapping;
use App\Models\IntegrationCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MergeCustomersAction
{
    /**
     * @throws ValidationException
     * @return array{customer: Customer, integration_customers_moved: int, report_schedules_moved: int}
     */
    public function execute(
        User $authUser,
        Company $company,
        int $primaryCustomerId,
        int $mergeCustomerId,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $primaryCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $primaryCustomerId)
            ->first();

        if (!$primaryCustomer) {
            throw ValidationException::withMessages([
                'customer' => ['Primary customer not found in your company.'],
            ]);
        }

        $mergeCustomer = Customer::where('company_id', $company->company_id)
            ->where('customer_id', $mergeCustomerId)
            ->first();

        if (!$mergeCustomer) {
            throw ValidationException::withMessages([
                'merge_customer_id' => ['Customer to merge not found in your company.'],
            ]);
        }

        if ($primaryCustomerId === $mergeCustomerId) {
            throw ValidationException::withMessages([
                'merge_customer_id' => ['Cannot merge a customer with itself.'],
            ]);
        }

        return DB::transaction(function () use ($authUser, $company, $primaryCustomer, $mergeCustomer, $ipAddress, $userAgent) {
            $integrationCustomersMoved = IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)->count();
            $reportSchedulesMoved = $mergeCustomer->reportSchedules()->count();

            // Move CustomerMapping records
            CustomerMapping::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);

            // Move IntegrationCustomer records
            IntegrationCustomer::where('customer_id', $mergeCustomer->customer_id)
                ->update(['customer_id' => $primaryCustomer->customer_id]);

            // Move ReportSchedules
            $mergeCustomer->reportSchedules()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            // Move GeneratedReports
            $mergeCustomer->generatedReports()->update([
                'customer_id' => $primaryCustomer->customer_id,
            ]);

            // Audit log
            AuditLog::create([
                'company_id' => $company->company_id,
                'event_type' => 'customers_merged',
                'actor_type' => 'User',
                'actor_id' => $authUser->user_id,
                'target_type' => 'Customer',
                'target_id' => $primaryCustomer->customer_id,
                'event_data' => json_encode([
                    'primary_customer' => [
                        'id' => $primaryCustomer->customer_id,
                        'name' => $primaryCustomer->canonical_name,
                    ],
                    'merged_customer' => [
                        'id' => $mergeCustomer->customer_id,
                        'name' => $mergeCustomer->canonical_name,
                    ],
                    'integration_customers_moved' => $integrationCustomersMoved,
                    'report_schedules_moved' => $reportSchedulesMoved,
                ]),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            $mergeCustomer->delete();

            return [
                'customer' => $primaryCustomer->load('integrationCustomers'),
                'integration_customers_moved' => $integrationCustomersMoved,
                'report_schedules_moved' => $reportSchedulesMoved,
            ];
        });
    }
}
