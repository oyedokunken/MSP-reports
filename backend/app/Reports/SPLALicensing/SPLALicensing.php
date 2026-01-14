<?php

namespace App\Reports\SPLALicensing;

use App\Models\Customer;
use App\Models\ActiveDirectoryLicenseAssignment;
use App\Models\ActiveDirectoryUser;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;

class SPLALicensing extends BaseReport
{
    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
    }

    public function getRequiredDataTypes(): array
    {
        return ['active_directory_license_assignment', 'active_directory_user'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 1; // Current data only
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        // Check if customer has any Active Directory integration
        $hasAD = $customer->integrationCustomers()
            ->whereHas('integration', function($query) {
                $query->where('integration_type', 'ActiveDirectory')
                      ->where('status', 'active');
            })
            ->exists();

        if (!$hasAD) {
            return false;
        }

        // Check if there are license assignments
        $hasLicenses = ActiveDirectoryLicenseAssignment::where('company_id', $this->companyId)
            ->where('customer_id', $customer->customer_id)
            ->exists();

        return $hasLicenses;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // 1. Fetch License Assignments
        $assignments = ActiveDirectoryLicenseAssignment::where('company_id', $this->companyId)
            ->where('customer_id', $customer->customer_id)
            ->get();

        // 2. Fetch User details for these assignments (to get Display Name, etc)
        // Note: The assignment model has user_sam_account_name. We need to map it to real users.
        $samAccountNames = $assignments->pluck('user_sam_account_name')->unique();
        
        $users = ActiveDirectoryUser::where('company_id', $this->companyId)
            ->where('customer_id', $customer->customer_id)
            ->whereIn('sam_account_name', $samAccountNames)
            ->get()
            ->keyBy('sam_account_name');

        // 3. Group by License Name (Product)
        $splaCounts = [];
        $grouped = $assignments->groupBy('license_name');

        foreach ($grouped as $licenseName => $items) {
            $userList = [];
            foreach ($items as $item) {
                $user = $users->get($item->user_sam_account_name);
                $userList[] = [
                    'upn' => $user ? $user->user_principal_name : $item->user_dn,
                    'display_name' => $user ? $user->display_name : ($item->user_sam_account_name),
                    'qualifying_groups' => [$item->assigned_via_group], // Agent sends single group source per row usually
                ];
            }
            
            $splaCounts[$licenseName] = [
                'count' => count($userList),
                'users' => $userList,
            ];
        }

        $totalSPLAUsers = $assignments->unique('user_sam_account_name')->count();

        // Identify products with users
        $activeProducts = array_keys($splaCounts);

        // Create executive summary
        $executiveSummary = [
            'total_spla_users' => $totalSPLAUsers,
            'active_products' => $activeProducts,
            'product_breakdown' => collect($splaCounts)->map(fn($data) => $data['count'])->toArray(),
        ];

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer,
            'customer_data' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $this->formatDate($startDate),
                'end' => $this->formatDate($endDate),
                'formatted' => $this->formatDateRange($startDate, $endDate),
            ],
            'executive_summary' => $executiveSummary,
            'spla_data' => $splaCounts,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/SPLALicensing/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 30, // seconds
        ];
    }
}
