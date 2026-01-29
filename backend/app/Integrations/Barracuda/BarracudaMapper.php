<?php

namespace App\Integrations\Barracuda;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BarracudaMapper
{
    /**
     * Map Barracuda account to customer format
     * 
     * @param array<string, mixed> $barracudaAccount
     * @return array<string, mixed>
     */
    public static function mapCustomer(array $barracudaAccount): array
    {
        return [
            'external_id' => $barracudaAccount['id'] ?? null,
            'name' => $barracudaAccount['name'] ?? 'Unknown',
            'active' => ($barracudaAccount['status'] ?? 'active') === 'active',
            'metadata' => [
                'source' => 'barracuda',
                'account_id' => $barracudaAccount['id'] ?? null,
                'email_domain' => $barracudaAccount['email_domain'] ?? null,
                'account_type' => $barracudaAccount['account_type'] ?? null,
                'created_at' => $barracudaAccount['created_at'] ?? null,
                'contact_email' => $barracudaAccount['contact_email'] ?? null,
                'contact_phone' => $barracudaAccount['contact_phone'] ?? null,
            ],
        ];
    }

    /**
     * Map email security data for historical storage
     * 
     * @param array<string, mixed> $barracudaThreat
     * @return array<string, mixed>
     */
    public static function mapSecurityData(array $barracudaThreat): array
    {
        return [
            'integration_customer_id' => $barracudaThreat['account_id'] ?? null,
            'data_type' => 'email_security',
            'timestamp' => $barracudaThreat['timestamp'] ?? now(),
            'payload' => [
                'threat_id' => $barracudaThreat['id'] ?? null,
                'threat_type' => $barracudaThreat['threat_type'] ?? 'unknown',
                'count' => $barracudaThreat['count'] ?? 0,
                'blocked' => $barracudaThreat['blocked'] ?? 0,
                'allowed' => $barracudaThreat['allowed'] ?? 0,
                'quarantined' => $barracudaThreat['quarantined'] ?? 0,
                'severity' => $barracudaThreat['severity'] ?? 'medium',
                'source_ip' => $barracudaThreat['source_ip'] ?? null,
                'destination_email' => $barracudaThreat['destination_email'] ?? null,
            ],
        ];
    }

    /**
     * Map spam filtering data for historical storage
     * 
     * @param array<string, mixed> $barracudaSpam
     * @return array<string, mixed>
     */
    public static function mapSpamData(array $barracudaSpam): array
    {
        return [
            'integration_customer_id' => $barracudaSpam['account_id'] ?? null,
            'data_type' => 'spam_filtering',
            'timestamp' => $barracudaSpam['timestamp'] ?? now(),
            'payload' => [
                'total_emails' => $barracudaSpam['total_emails'] ?? 0,
                'spam_blocked' => $barracudaSpam['spam_blocked'] ?? 0,
                'spam_percentage' => $barracudaSpam['spam_percentage'] ?? 0,
                'clean_emails' => $barracudaSpam['clean_emails'] ?? 0,
                'quarantined' => $barracudaSpam['quarantined'] ?? 0,
                'false_positives' => $barracudaSpam['false_positives'] ?? 0,
            ],
        ];
    }

    /**
     * Map compliance report data for historical storage
     * 
     * @param array<string, mixed> $barracudaCompliance
     * @return array<string, mixed>
     */
    public static function mapComplianceData(array $barracudaCompliance): array
    {
        return [
            'integration_customer_id' => $barracudaCompliance['account_id'] ?? null,
            'data_type' => 'compliance_reports',
            'timestamp' => $barracudaCompliance['timestamp'] ?? now(),
            'payload' => [
                'report_id' => $barracudaCompliance['id'] ?? null,
                'report_type' => $barracudaCompliance['report_type'] ?? 'general',
                'compliance_status' => $barracudaCompliance['status'] ?? 'compliant',
                'violations' => $barracudaCompliance['violations'] ?? [],
                'recommendations' => $barracudaCompliance['recommendations'] ?? [],
                'audit_trail' => $barracudaCompliance['audit_trail'] ?? [],
            ],
        ];
    }
}
