<?php

namespace App\Integrations\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
    protected int $statusCode;
    protected ?string $responseBody;

    public function __construct(string $message, int $statusCode = 401, ?string $responseBody = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Get a user-friendly error message based on the status code
     */
    public function getUserMessage(): string
    {
        return match($this->statusCode) {
            400 => 'Invalid request. Please check your credentials and try again.',
            401 => 'Authentication failed. Please verify your API credentials.',
            403 => 'Access forbidden. Your API key may not have sufficient permissions.',
            404 => 'API endpoint not found. Please verify your endpoint URL.',
            429 => 'Rate limit exceeded. Please try again later.',
            500, 502, 503 => 'The API service is currently unavailable. Please try again later.',
            default => 'Authentication failed: ' . $this->getMessage(),
        };
    }
}
