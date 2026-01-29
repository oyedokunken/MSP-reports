<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base exception for all integration-related errors
 */
class IntegrationException extends Exception
{
    protected string $integrationId;
    protected string $integrationType;

    public function __construct(
        string $message,
        string $integrationId = '',
        string $integrationType = '',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->integrationId = $integrationId;
        $this->integrationType = $integrationType;
    }

    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function getIntegrationType(): string
    {
        return $this->integrationType;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'integration_id' => $this->integrationId,
            'integration_type' => $this->integrationType,
            'message' => $this->getMessage(),
        ];
    }
}
