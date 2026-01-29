<?php

declare(strict_types=1);

namespace App\Integrations\Exceptions;

use Exception;

final class RateLimitException extends Exception
{
    public int $retryAfter;

    public function __construct(string $message = "Rate limit exceeded", int $retryAfter = 60)
    {
        parent::__construct($message, 429);
        $this->retryAfter = $retryAfter;
    }
}
