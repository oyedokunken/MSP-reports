<?php

declare(strict_types=1);

namespace App\Integrations\Exceptions;

use App\Exceptions\ApiResponseException as BaseApiResponseException;

/**
 * @deprecated Use App\Exceptions\ApiResponseException instead
 */
final class ApiResponseException extends BaseApiResponseException
{
}
