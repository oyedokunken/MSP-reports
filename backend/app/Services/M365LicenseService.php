<?php

declare(strict_types=1);

namespace App\Services;

final class M365LicenseService
{
    /**
     * Common Microsoft 365 SKU IDs to Friendly Names
     * @see https://learn.microsoft.com/en-us/azure/active-directory/enterprise-users/licensing-service-plan-reference
     */
    /** @var array<string, string> */
    protected static array $skuMap = [
        '6fd2c87f-b296-42f0-b197-1e91e994b900' => 'Office 365 E3',
        'f245aec8-d61f-4283-9aed-279269599393' => 'Office 365 E3 (GCC)',
        '18181a46-0d4e-45cd-891e-60aabd171b4e' => 'Office 365 E1',
        '06ebc4ee-1bb5-47dd-8120-11324bc54e06' => 'Office 365 E5',
        '05e9a617-0261-4cee-bb44-138d3ef5d965' => 'Microsoft 365 E3',
        'c7df2760-2c81-4ef7-b578-5b5392b571df' => 'Microsoft 365 E5',
        '28608785-ceaf-4904-8d61-00a4b6070622' => 'Microsoft 365 Business Standard',
        'f38c3397-28f0-4d51-8742-1268f7f259fe' => 'Microsoft 365 Business Premium',
        '617b0978-0632-4c4f-abaa-66d4885568b5' => 'Microsoft 365 Business Basic',
        '4b585984-651b-4c2a-932a-1797ad170076' => 'Microsoft 365 F3',
        'a40a5f42-494b-489d-92d6-930f14dc9905' => 'Microsoft 365 F1',
        'efcc9494-6ef8-43d4-ba36-3b793b37868c' => 'Enterprise Mobility + Security E3',
        'b7dc903e-0931-451d-8a30-503440e15408' => 'Enterprise Mobility + Security E5',
    ];

    /**
     * Get friendly name for a SKU ID or Part Number
     */
    public function getFriendlyName(string $skuId, ?string $skuPartNumber = null): string
    {
        if (isset(self::$skuMap[$skuId])) {
            return self::$skuMap[$skuId];
        }

        if ($skuPartNumber) {
            // Clean up common SKU Part Numbers (e.g., ENTERPRISEPACK -> Office 365 E3)
            return $this->formatPartNumber($skuPartNumber);
        }

        return $skuId;
    }

    /**
     * Heuristics for formatting unknown SKU part numbers
     */
    protected function formatPartNumber(string $partNumber): string
    {
        $map = [
            'ENTERPRISEPACK' => 'Office 365 E3',
            'ENTERPRISEPREMIUM' => 'Office 365 E5',
            'STANDARDPACK' => 'Office 365 E1',
            'O365_BUSINESS_PREMIUM' => 'Microsoft 365 Business Standard',
            'SPB' => 'Microsoft 365 Business Premium',
            'O365_BUSINESS' => 'Microsoft 365 Apps for business',
        ];

        return $map[$partNumber] ?? str_replace('_', ' ', $partNumber);
    }
}
