<?php

namespace App\Services;

use App\Models\ActiveDirectoryUser;
use App\Models\ActiveDirectoryGroup;
use App\Models\ActiveDirectoryGroupMembership;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActiveDirectorySPLAService
{
    /**
     * Default keywords for SPLA product discovery in AD groups
     */
    protected array $productKeywords = [
        'RDS' => ['RDS', 'Remote Desktop', 'Terminal Server', 'TSC'],
        'SQL' => ['SQL Server', 'MSSQL'],
        'Office' => ['Office', 'O365', 'M365'],
        'Windows Server' => ['Windows Server', 'WinSvr'],
    ];

    /**
     * Get SPLA license counts for a specific customer
     */
    public function getSPLACounts(int $companyId, int $customerId): array
    {
        $results = [];
        
        foreach ($this->productKeywords as $product => $keywords) {
            $users = $this->getUsersForProduct($companyId, $customerId, $keywords);
            
            $results[$product] = [
                'count' => $users->count(),
                'users' => $users->map(function ($user) {
                    return [
                        'upn' => $user->user_principal_name,
                        'display_name' => $user->display_name,
                        'qualifying_groups' => $user->qualifying_groups,
                    ];
                })->toArray(),
            ];
        }

        return $results;
    }

    /**
     * Identify unique users in groups containing specific keywords
     */
    protected function getUsersForProduct(int $companyId, int $customerId, array $keywords): Collection
    {
        // 1. Find all groups matching keywords
        $groupIds = ActiveDirectoryGroup::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('description', 'LIKE', "%{$keyword}%");
                }
            })
            ->pluck('distinguished_name');

        if ($groupIds->isEmpty()) {
            return collect();
        }

        // 2. Get unique users in those groups
        // We join with memberships to find users
        $users = ActiveDirectoryUser::where('active_directory_users.company_id', $companyId)
            ->where('active_directory_users.customer_id', $customerId)
            ->where('enabled', true)
            ->join('active_directory_group_memberships', 'active_directory_users.sam_account_name', '=', 'active_directory_group_memberships.user_sam_account_name')
            ->whereIn('active_directory_group_memberships.group_dn', $groupIds)
            ->where('active_directory_group_memberships.company_id', $companyId)
            ->select('active_directory_users.*', 'active_directory_group_memberships.group_name')
            ->get();

        // Group by User to handle multiple group memberships
        return $users->groupBy('user_principal_name')->map(function ($userGroup) {
            $user = $userGroup->first();
            $user->qualifying_groups = $userGroup->pluck('group_name')->unique()->values()->toArray();
            return $user;
        });
    }

    /**
     * Allow customizing keywords per customer/company
     */
    public function setProductKeywords(array $keywords): void
    {
        $this->productKeywords = $keywords;
    }
}
