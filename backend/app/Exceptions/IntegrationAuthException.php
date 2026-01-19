<?php

namespace App\Exceptions;

/**
 * Exception thrown when integration authentication fails
 */
class IntegrationAuthException extends IntegrationException
{
    public function __construct(
        string $message = 'Integration authentication failed',
        string $integrationId = '',
        string $integrationType = '',
        ?Exception $previous = null
    ) {
        parent::__construct($message, $integrationId, $integrationType, 401, $previous);
    }
}
