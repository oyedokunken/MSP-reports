<?php

declare(strict_types=1);

namespace App\Integrations\Exceptions;

use App\Exceptions\RateLimitExceededException as BaseRateLimitExceededException;

/**
 * @deprecated Use App\Exceptions\RateLimitExceededException instead
 */
final class RateLimitExceededException extends BaseRateLimitExceededException
{
}
