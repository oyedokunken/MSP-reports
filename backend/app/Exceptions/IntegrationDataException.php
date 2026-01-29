<?php

namespace App\Exceptions;

/**
 * Exception thrown when integration data is invalid
 */
class IntegrationDataException extends IntegrationException
{
    /** @var array<string, mixed> */
    protected array $invalidData;

    /**
     * @param array<string, mixed> $invalidData
     */
    public function __construct(
        string $message = 'Invalid integration data',
        array $invalidData = [],
        string $integrationId = '',
        string $integrationType = '',
        ?\Exception $previous = null
    ) {
        parent::__construct($message, $integrationId, $integrationType, 422, $previous);
        $this->invalidData = $invalidData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvalidData(): array
    {
        return $this->invalidData;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'invalid_data' => $this->invalidData,
        ]);
    }
}
