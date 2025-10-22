<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SecureHeaders;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        // Register services
    }

    public function boot(): void
    {
        // Register middleware
        app('router')->aliasMiddleware('secure.headers', SecureHeaders::class);
    }
}
