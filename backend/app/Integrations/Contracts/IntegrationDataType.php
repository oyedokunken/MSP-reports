<?php

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

    // BitDefender Types
    public const ENDPOINT = 'endpoint';
    public const THREAT = 'threat';

    // Generic Types
    public const LICENSE = 'license';
    public const USER = 'user';
    public const SECURITY_EVENT = 'security_event';

    /**
     * Get all valid data types
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
            self::ENDPOINT,
            self::THREAT,
            self::LICENSE,
            self::USER,
            self::SECURITY_EVENT,
        ];
    }

    /**
     * Get ITGlue specific types
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
     * Check if a data type is valid
     */
    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
