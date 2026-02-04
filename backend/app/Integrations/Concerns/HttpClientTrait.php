<?php

declare(strict_types=1);

namespace App\Integrations\Concerns;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

trait HttpClientTrait
{
    /**
     * Create an HTTP client with appropriate SSL settings for the environment.
     * In development, SSL verification is disabled to avoid certificate issues.
     * In production, SSL verification is enabled for security.
     */
    protected function createHttpClient(): PendingRequest
    {
        return Http::withOptions([
            'verify' => config('app.env') === 'production',
        ]);
    }
}
