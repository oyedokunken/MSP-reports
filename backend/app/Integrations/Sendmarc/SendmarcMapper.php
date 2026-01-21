<?php

namespace App\Integrations\Sendmarc;

use Carbon\Carbon;

class SendmarcMapper
{
    public static function mapDomain(array $domain): array
    {
        return [
            'external_id' => $domain['uuid'] ?? $domain['domain_name'] ?? 'unknown',
            'name' => $domain['domain_name'] ?? 'Unknown Domain',
            'active' => true,
            'metadata' => [
                'uuid' => $domain['uuid'] ?? null,
                'domain_name' => $domain['domain_name'] ?? null,
                'account_uuid' => $domain['account_uuid'] ?? null,
                'suid' => $domain['suid'] ?? null,
                'type' => $domain['type'] ?? null,
                'is_favourite' => $domain['is_favourite'] ?? false,
                'score' => $domain['score'] ?? 0,
                'enable_dkim_public_keys' => $domain['enable_dkim_public_keys'] ?? false,
                'enable_bimi' => $domain['enable_bimi'] ?? false,
                'raw_data' => $domain,
            ],
        ];
    }

    public static function mapSenderStatistics(array $sender, string $domainUuid, $timestamp = null): array
    {
        return [
            'data_type' => 'sender_statistics',
            'timestamp' => $timestamp ?? now(),
            'payload' => [
                'sender_uuid' => $sender['uuid'] ?? null,
                'domain_uuid' => $domainUuid,
                'organisation' => $sender['organisation'] ?? null,
                'service' => $sender['service'] ?? null,
                'service_type' => $sender['service_type'] ?? null,
                'category' => $sender['category'] ?? 'unknown',
                'spf_value' => $sender['spf_value'] ?? null,
                'is_spf_configured' => $sender['is_spf_configured'] ?? false,
                'total_incidents' => $sender['total_incidents'] ?? 0,
                'total_threat_incidents' => $sender['total_threat_incidents'] ?? 0,
                'total_blacklisted_incidents' => $sender['total_blacklisted_incidents'] ?? 0,
                'total_complete_passes' => $sender['total_complete_passes'] ?? 0,
                'total_dkim_fails_spf_passes' => $sender['total_dkim_fails_spf_passes'] ?? 0,
                'total_complete_failures' => $sender['total_complete_failures'] ?? 0,
                'total_overrides_none' => $sender['total_overrides_none'] ?? 0,
                'total_overrides_quarantine' => $sender['total_overrides_quarantine'] ?? 0,
                'total_overrides_reject' => $sender['total_overrides_reject'] ?? 0,
                'total_spf_fails_dkim_passes' => $sender['total_spf_fails_dkim_passes'] ?? 0,
                'total_forwards' => $sender['total_forwards'] ?? 0,
                'timeline' => $sender['timeline'] ?? null,
            ],
        ];
    }

    public static function mapVolumeTotals(array $volumeData, string $domainUuid, $timestamp = null): array
    {
        return [
            'data_type' => 'volume_totals',
            'timestamp' => $timestamp ?? now(),
            'payload' => [
                'domain_uuid' => $domainUuid,
                'total_incidents' => $volumeData['total_incidents'] ?? 0,
                'total_complete_passes' => $volumeData['total_complete_passes'] ?? 0,
                'total_dkim_fails_spf_passes' => $volumeData['total_dkim_fails_spf_passes'] ?? 0,
                'total_complete_failures' => $volumeData['total_complete_failures'] ?? 0,
                'total_overrides_none' => $volumeData['total_overrides_none'] ?? 0,
                'total_overrides_quarantine' => $volumeData['total_overrides_quarantine'] ?? 0,
                'total_overrides_reject' => $volumeData['total_overrides_reject'] ?? 0,
                'total_spf_fails_dkim_passes' => $volumeData['total_spf_fails_dkim_passes'] ?? 0,
                'total_sources' => $volumeData['total_sources'] ?? 0,
            ],
        ];
    }

    public static function mapVolumeTimeline(array $timelineData, string $domainUuid, int $index, $timestamp = null): array
    {
        return [
            'data_type' => 'volume_timeline',
            'timestamp' => $timestamp ?? now(),
            'payload' => [
                'domain_uuid' => $domainUuid,
                'timeline_index' => $index,
                'total_incidents' => $timelineData['total_incidents'] ?? 0,
                'total_complete_passes' => $timelineData['total_complete_passes'] ?? 0,
                'total_dkim_fails_spf_passes' => $timelineData['total_dkim_fails_spf_passes'] ?? 0,
                'total_complete_failures' => $timelineData['total_complete_failures'] ?? 0,
                'total_overrides_none' => $timelineData['total_overrides_none'] ?? 0,
                'total_overrides_quarantine' => $timelineData['total_overrides_quarantine'] ?? 0,
                'total_overrides_reject' => $timelineData['total_overrides_reject'] ?? 0,
                'total_spf_fails_dkim_passes' => $timelineData['total_spf_fails_dkim_passes'] ?? 0,
                'total_sources' => $timelineData['total_sources'] ?? 0,
            ],
        ];
    }

    public static function mapIpAddressStatistics(array $ipData, string $domainUuid, $timestamp = null): array
    {
        return [
            'data_type' => 'ip_address_statistics',
            'timestamp' => $timestamp ?? now(),
            'payload' => [
                'source_uuid' => $ipData['uuid'] ?? null,
                'domain_uuid' => $domainUuid,
                'ip_address' => $ipData['ip_address'] ?? null,
                'hostname' => $ipData['hostname'] ?? null,
                'organisation' => $ipData['organisation'] ?? null,
                'service' => $ipData['service'] ?? null,
                'category' => $ipData['category'] ?? 'unknown',
                'total_incidents' => $ipData['total_incidents'] ?? 0,
                'total_complete_passes' => $ipData['total_complete_passes'] ?? 0,
                'total_complete_failures' => $ipData['total_complete_failures'] ?? 0,
            ],
        ];
    }
}
