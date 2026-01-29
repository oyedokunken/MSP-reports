<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\ActiveDirectoryLicenseAssignment;
use App\Models\ActiveDirectoryUser;

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
    protected $description = 'Determine SPLA licensing counts from Agent Synced Data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customerId = $this->argument('customer_id');
        $customer = Customer::find($customerId);

        if (!$customer) {
            $this->error("Customer with ID {$customerId} not found.");
            return 1;
        }

        $this->info("Checking SPLA licensing for Customer: {$customer->canonical_name} (#{$customerId}) (Source: Agent Data)");

        try {
            // 1. Fetch License Assignments
            $assignments = ActiveDirectoryLicenseAssignment::where('company_id', $customer->company_id)
                ->where('customer_id', $customer->customer_id)
                ->get();

            if ($assignments->isEmpty()) {
                $this->warn('No SPLA records found in active_directory_license_assignments table.');
                $this->line('Ensure the AD Agent is running with the new licenseGroups configuration.');
                return 0;
            }

            // 2. Fetch User details for these assignments
            $samAccountNames = $assignments->pluck('user_sam_account_name')->unique();
            
            $users = ActiveDirectoryUser::where('company_id', $customer->company_id)
                ->where('customer_id', $customer->customer_id)
                ->whereIn('sam_account_name', $samAccountNames)
                ->get()
                ->keyBy('sam_account_name');

            // 3. Group by License Name
            $grouped = $assignments->groupBy('license_name');

            foreach ($grouped as $licenseName => $items) {
                $this->newLine();
                $this->info("Product: {$licenseName} - Total Count: {$items->count()}");

                $headers = ['User UPN', 'Display Name', 'Source Group'];
                $rows = [];
                
                foreach ($items as $item) {
                    $user = $users->get($item->user_sam_account_name);
                    $rows[] = [
                        $user ? $user->user_principal_name : ($item->user_dn ?? 'N/A'),
                        $user ? $user->display_name : ($item->user_sam_account_name),
                        $item->assigned_via_group,
                    ];
                }

                $this->table($headers, $rows);
            }

        } catch (\Exception $e) {
            $this->error('Failed to retrieve SPLA counts: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
