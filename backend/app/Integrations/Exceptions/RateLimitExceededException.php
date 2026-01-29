<?php

namespace App\Integrations\Exceptions;

use App\Exceptions\RateLimitExceededException as BaseRateLimitExceededException;

/**
 * @deprecated Use App\Exceptions\RateLimitExceededException instead
 */
class RateLimitExceededException extends BaseRateLimitExceededException
{
}
