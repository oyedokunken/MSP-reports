<?php

declare(strict_types=1);

namespace App\Integrations\Contracts;

/**
 * Constants for integration data types
 * Eliminates magic strings throughout the codebase
 * Values match what's stored in the database
 */
final class IntegrationDataType
{
    // ITGlue Types (match DB values)
    public const FLEXIBLE_ASSETS = 'flexible_assets';
    public const SSL_CERTIFICATE = 'ssl_certificate';
    public const DOMAINS = 'domains';
    public const ORGANIZATION_INFO = 'organization_info';

    // Datto Types
    public const DEVICE_STATUS = 'device_status';
    public const ALERT = 'alert';
    public const UPTIME_METRIC = 'uptime_metric';
    public const BACKUP_STATUS = 'backup_status';

    // Autotask Types
    public const TICKET = 'ticket';
    public const CONTRACT = 'contract';
    public const CONFIGURATION_ITEM = 'configuration_item';
    public const TIME_ENTRY = 'time_entry';
    public const CONTACT = 'contact';
    public const TICKET_NOTE = 'ticket_note';
    public const RESOURCE = 'resource';

    // BitDefender Types
    public const ENDPOINT = 'endpoint';
    public const THREAT = 'threat';

    // Veeam ONE Types
    public const VEEAM_BACKUP_JOB = 'veeam_backup_job';
    public const VEEAM_BACKUP_SESSION = 'veeam_backup_session';
    public const VEEAM_ALARM = 'veeam_alarm';
    public const VEEAM_REPOSITORY = 'veeam_repository';
    public const VEEAM_PROTECTED_WORKLOAD = 'veeam_protected_workload';

    // CIPP Types
    public const SECURITY_ALERT = 'security_alert';
    public const SECURITY_INCIDENT = 'security_incident';
    public const CONDITIONAL_ACCESS = 'conditional_access';
    public const DEFENDER_STATE = 'defender_state';
    public const MAILBOX_USAGE = 'mailbox_usage';
    public const EXCHANGE_PROTECTION = 'exchange_protection';

    // Generic Types
    public const LICENSE = 'license';
    public const USER = 'user';
    public const SECURITY_EVENT = 'security_event';

    /**
     * Get all valid data types
     * 
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::FLEXIBLE_ASSETS,
            self::SSL_CERTIFICATE,
            self::DOMAINS,
            self::ORGANIZATION_INFO,
            self::DEVICE_STATUS,
            self::ALERT,
            self::UPTIME_METRIC,
            self::BACKUP_STATUS,
            self::TICKET,
            self::CONTRACT,
            self::CONFIGURATION_ITEM,
            self::TIME_ENTRY,
            self::CONTACT,
            self::TICKET_NOTE,
            self::RESOURCE,
            self::ENDPOINT,
            self::THREAT,
            self::VEEAM_BACKUP_JOB,
            self::VEEAM_BACKUP_SESSION,
            self::VEEAM_ALARM,
            self::VEEAM_REPOSITORY,
            self::VEEAM_PROTECTED_WORKLOAD,
            self::LICENSE,
            self::USER,
            self::SECURITY_EVENT,
            self::SECURITY_ALERT,
            self::SECURITY_INCIDENT,
            self::CONDITIONAL_ACCESS,
            self::DEFENDER_STATE,
            self::MAILBOX_USAGE,
            self::EXCHANGE_PROTECTION,
        ];
    }

    /**
     * Get ITGlue specific types
     * 
     * @return array<int, string>
     */
    public static function itglue(): array
    {
        return [
            self::FLEXIBLE_ASSETS,
            self::SSL_CERTIFICATE,
            self::DOMAINS,
        ];
    }

    /**
     * Get Datto specific types
     * 
     * @return array<int, string>
     */
    public static function datto(): array
    {
        return [
            self::DEVICE_STATUS,
            self::ALERT,
            self::UPTIME_METRIC,
            self::BACKUP_STATUS,
        ];
    }

    /**
     * Get Veeam ONE specific types
     * 
     * @return array<int, string>
     */
    public static function veeamOne(): array
    {
        return [
            self::VEEAM_BACKUP_JOB,
            self::VEEAM_BACKUP_SESSION,
            self::VEEAM_ALARM,
            self::VEEAM_REPOSITORY,
            self::VEEAM_PROTECTED_WORKLOAD,
        ];
    }

    /**
     * Check if a data type is valid
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
