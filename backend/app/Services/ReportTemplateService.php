<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\Customer;
use App\Models\ReportSchedule;
use Illuminate\Support\Collection;

class ReportTemplateService
{
    /**
     * Apply template to a single customer (COPY settings, not link)
     */
    public function applyTemplateToCustomer(ReportTemplate $template, Customer $customer): ReportSchedule
    {
        // Create a new schedule by COPYING template values
        $schedule = ReportSchedule::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->customer_id,
            'report_type' => $template->report_type,
            'frequency' => $template->frequency,
            'schedule_config' => $template->schedule_config, // COPY, not reference
            'time_of_day' => $template->time_of_day,
            'timezone' => $template->timezone ?? 'Australia/Brisbane',
            'active' => true,
        ]);
        
        // Store additional settings in customer's report_configuration
        if ($template->included_sections || $template->delivery_settings || $template->report_options) {
            $customer->update([
                'report_configuration' => array_merge(
                    $customer->report_configuration ?? [],
                    [
                        'included_sections' => $template->included_sections,
                        'delivery_settings' => $template->delivery_settings,
                        'report_options' => $template->report_options,
                    ]
                ),
            ]);
        }
        
        return $schedule->fresh();
    }
    
    /**
     * Apply template to multiple customers
     */
    public function applyTemplateToCustomers(ReportTemplate $template, array $customerIds): Collection
    {
        $schedules = collect();
        
        foreach ($customerIds as $customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                $schedules->push($this->applyTemplateToCustomer($template, $customer));
            }
        }
        
        return $schedules;
    }
}
