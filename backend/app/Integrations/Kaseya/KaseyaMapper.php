<?php

namespace App\Integrations\Kaseya;

class KaseyaMapper
{
    /**
     * Map Kaseya compliance assessment into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $assessment
     * @return array<string, mixed>
     */
    public static function mapComplianceAssessment(array $assessment): array
    {
        return [
            // No real schema yet; filled in once Kaseya API is defined
            'integration_customer_id' => null,
            'data_type' => 'kaseya_compliance_assessment',
            'timestamp' => now(),
            'payload' => $assessment,
        ];
    }

    /**
     * Map Kaseya risk assessment into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $assessment
     * @return array<string, mixed>
     */
    public static function mapRiskAssessment(array $assessment): array
    {
        return [
            // No real schema yet; filled in once Kaseya API is defined
            'integration_customer_id' => null,
            'data_type' => 'kaseya_risk_assessment',
            'timestamp' => now(),
            'payload' => $assessment,
        ];
    }

    /**
     * Map Kaseya audit report into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public static function mapAuditReport(array $report): array
    {
        return [
            // No real schema yet; filled in once Kaseya API is defined
            'integration_customer_id' => null,
            'data_type' => 'kaseya_audit_report',
            'timestamp' => now(),
            'payload' => $report,
        ];
    }
}
