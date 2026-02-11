<?php

declare(strict_types=1);

namespace App\Integrations\Vonahi;

use Carbon\Carbon;

final class VonahiMapper
{
    /**
     * Map Vonahi company to standardized customer format.
     *
     * API fields: id, full_name, short_name, industry_id, time_zone, trial_company, created_at, updated_at
     *
     * @param array<string, mixed> $company
     * @return array<string, mixed>
     */
    public static function mapCompany(array $company): array
    {
        $id = $company['id'] ?? null;
        $name = $company['full_name'] ?? $company['short_name'] ?? 'Unknown Company';

        return [
            'external_id' => (string) $id,
            'name' => $name,
            'active' => true,
            'metadata' => [
                'company_id' => $id,
                'full_name' => $company['full_name'] ?? null,
                'short_name' => $company['short_name'] ?? null,
                'industry_id' => $company['industry_id'] ?? null,
                'time_zone' => $company['time_zone'] ?? null,
                'trial_company' => $company['trial_company'] ?? false,
                'auto_purge_report_after' => $company['auto_purge_report_after'] ?? null,
                'created_at' => $company['created_at'] ?? null,
                'updated_at' => $company['updated_at'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi assessment to standardized IntegrationData payload.
     *
     * API fields: id, company_id, name, severity, created_at, updated_at,
     *   remediation_scan, assessment_display_name, paused, paused_at,
     *   agent_ip, agent_subnet, agent_dns
     *
     * @param array<string, mixed> $assessment
     * @return array<string, mixed>
     */
    public static function mapAssessment(array $assessment, ?int $integrationCustomerId = null): array
    {
        $id = $assessment['id'] ?? null;
        $timestamp = $assessment['updated_at'] ?? $assessment['created_at'] ?? now();

        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_assessment',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'company_id' => $assessment['company_id'] ?? null,
                'name' => $assessment['name'] ?? 'Unknown Assessment',
                'display_name' => $assessment['assessment_display_name'] ?? null,
                'severity' => $assessment['severity'] ?? 'informational',
                'remediation_scan' => $assessment['remediation_scan'] ?? false,
                'paused' => $assessment['paused'] ?? false,
                'paused_at' => $assessment['paused_at'] ?? null,
                'agent_ip' => $assessment['agent_ip'] ?? null,
                'agent_subnet' => $assessment['agent_subnet'] ?? null,
                'agent_dns' => $assessment['agent_dns'] ?? null,
                'created_at' => $assessment['created_at'] ?? null,
                'updated_at' => $assessment['updated_at'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi finding to standardized IntegrationData payload.
     *
     * API fields: id, assessment_id, name, description, severity, references,
     *   security_impact, reproduction_steps, recommendation, affected_urls,
     *   evidence_data, affected_hosts_count, affected_host_ips, created_at, updated_at
     *
     * @param array<string, mixed> $finding
     * @return array<string, mixed>
     */
    public static function mapFinding(array $finding, ?int $integrationCustomerId = null): array
    {
        $id = $finding['id'] ?? null;
        $timestamp = $finding['created_at'] ?? now();

        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_finding',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'assessment_id' => $finding['assessment_id'] ?? null,
                'name' => $finding['name'] ?? 'Unknown Finding',
                'description' => $finding['description'] ?? null,
                'severity' => $finding['severity'] ?? 'Informational',
                'references' => $finding['references'] ?? null,
                'security_impact' => $finding['security_impact'] ?? null,
                'reproduction_steps' => $finding['reproduction_steps'] ?? null,
                'recommendation' => $finding['recommendation'] ?? null,
                'affected_urls' => $finding['affected_urls'] ?? null,
                'evidence_data' => $finding['evidence_data'] ?? null,
                'affected_hosts_count' => $finding['affected_hosts_count'] ?? 0,
                'affected_host_ips' => $finding['affected_host_ips'] ?? [],
                'created_at' => $finding['created_at'] ?? null,
                'updated_at' => $finding['updated_at'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi report to standardized IntegrationData payload.
     *
     * API fields: id, assessment_id, created_at, updated_at, purge_date,
     *   released_on, executive_summary_url, technical_report_url,
     *   ai_enhanced_executive_summary_url, ai_enhanced_version_url,
     *   supporting_evidence_url
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public static function mapReport(array $report, ?int $integrationCustomerId = null): array
    {
        $id = $report['id'] ?? null;
        $timestamp = $report['released_on'] ?? $report['created_at'] ?? now();

        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_report',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'assessment_id' => $report['assessment_id'] ?? null,
                'purge_date' => $report['purge_date'] ?? null,
                'released_on' => $report['released_on'] ?? null,
                'executive_summary_url' => $report['executive_summary_url'] ?? null,
                'technical_report_url' => $report['technical_report_url'] ?? null,
                'ai_enhanced_executive_summary_url' => $report['ai_enhanced_executive_summary_url'] ?? null,
                'ai_enhanced_version_url' => $report['ai_enhanced_version_url'] ?? null,
                'supporting_evidence_url' => $report['supporting_evidence_url'] ?? null,
                'created_at' => $report['created_at'] ?? null,
                'updated_at' => $report['updated_at'] ?? null,
            ],
        ];
    }

    /**
     * Map Vonahi agent to standardized IntegrationData payload.
     *
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    public static function mapAgent(array $agent, ?int $integrationCustomerId = null): array
    {
        $id = $agent['id'] ?? null;
        $timestamp = $agent['updated_at'] ?? $agent['created_at'] ?? now();

        return [
            'integration_customer_id' => $integrationCustomerId,
            'data_type' => 'vonahi_agent',
            'timestamp' => Carbon::parse($timestamp),
            'payload' => [
                'id' => $id,
                'company_id' => $agent['company_id'] ?? null,
                'name' => $agent['name'] ?? 'Unknown Agent',
                'private_ip' => $agent['private_ip'] ?? null,
                'public_ip' => $agent['public_ip'] ?? null,
                'subnet_mask' => $agent['subnet_mask'] ?? null,
                'default_gateway' => $agent['default_gateway'] ?? null,
                'dns_server' => $agent['dns_server'] ?? [],
                'uuid' => $agent['uuid'] ?? null,
                'activation_status' => $agent['activation_status'] ?? null,
                'network_status' => $agent['network_status'] ?? null,
                'last_heartbeat' => $agent['last_heartbeat'] ?? null,
                'agent_type' => $agent['agent_type'] ?? null,
                'mac' => $agent['mac'] ?? null,
                'network_interface' => $agent['network_interface'] ?? null,
                'location_id' => $agent['location_id'] ?? null,
                'registration_date' => $agent['registration_date'] ?? null,
                'created_at' => $agent['created_at'] ?? null,
                'updated_at' => $agent['updated_at'] ?? null,
            ],
        ];
    }
}
