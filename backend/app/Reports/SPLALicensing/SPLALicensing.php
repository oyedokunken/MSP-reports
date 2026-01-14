<?php

namespace App\Reports\SPLALicensing;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use Carbon\Carbon;
use App\Services\ActiveDirectorySPLAService;

class SPLALicensing extends BaseReport
{
    protected ActiveDirectorySPLAService $splaService;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->splaService = new ActiveDirectorySPLAService();
    }

    public function getRequiredDataTypes(): array
    {
        return ['active_directory_user', 'active_directory_group'];
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

        // Check if there are users and groups mapped
        // Note: We check if any AD users exist for this customer
        $hasUsers = \App\Models\ActiveDirectoryUser::where('company_id', $this->companyId)
            ->where('customer_id', $customer->customer_id)
            ->exists();

        return $hasUsers;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        // Check for custom keywords in options or customer config
        $customKeywords = $options['keywords'] ?? $customer->report_configuration['spla_keywords'] ?? null;

        if ($customKeywords) {
            $this->splaService->setProductKeywords($customKeywords);
        }

        $splaCounts = $this->splaService->getSPLACounts($this->companyId, $customer->customer_id);

        $totalSPLAUsers = collect($splaCounts)->sum('count');

        // Identify products with users
        $activeProducts = collect($splaCounts)
            ->filter(fn($data) => $data['count'] > 0)
            ->keys()
            ->toArray();

        // Create executive summary
        $executiveSummary = [
            'total_spla_users' => $totalSPLAUsers,
            'active_products' => $activeProducts,
            'product_breakdown' => collect($splaCounts)->map(fn($data) => $data['count'])->filter(fn($c) => $c > 0)->toArray(),
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
