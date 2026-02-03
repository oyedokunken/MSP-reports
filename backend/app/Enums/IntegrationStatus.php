<?php

declare(strict_types=1);

namespace App\Enums;

enum IntegrationStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
    case SYNCING = 'syncing';
    case IMPORTING_HISTORICAL = 'importing_historical';

    public function isHealthy(): bool
    {
        return $this === self::ACTIVE || $this === self::SYNCING || $this === self::IMPORTING_HISTORICAL;
    }
}
