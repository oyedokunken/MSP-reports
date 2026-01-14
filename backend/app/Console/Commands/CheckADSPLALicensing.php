<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Integration;
use App\Services\ActiveDirectorySPLAService;

class CheckADSPLALicensing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad:spla-licensing {customer_id : The ID of the customer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Determine SPLA licensing counts for a customer based on AD groups';

    /**
     * Execute the console command.
     */
    public function handle(ActiveDirectorySPLAService $splaService)
    {
        $customerId = $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer with ID {$customerId} not found.");
            return 1;
        }

        $this->info("Checking SPLA licensing for Customer: {$customer->name} (#{$customerId})");

        try {
            $results = $splaService->getSPLACounts($customer->company_id, $customer->customer_id);

            if (empty($results)) {
                $this->warn('No SPLA products identified.');
                return 0;
            }

            foreach ($results as $product => $data) {
                $this->newLine();
                $this->info("Product: {$product} - Total Count: {$data['count']}");

                if ($data['count'] > 0) {
                    $headers = ['User UPN', 'Display Name', 'Qualifying Groups'];
                    $rows = array_map(function ($user) {
                        return [
                            $user['upn'],
                            $user['display_name'],
                            implode(', ', $user['qualifying_groups']),
                        ];
                    }, $data['users']);

                    $this->table($headers, $rows);
                } else {
                    $this->line("No users found for {$product}.");
                }
            }

        } catch (\Exception $e) {
            $this->error('Failed to calculate SPLA counts: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
