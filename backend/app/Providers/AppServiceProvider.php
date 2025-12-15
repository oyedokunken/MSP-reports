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
        // Configure database to store timestamps in UTC
        // while keeping PHP timezone (Australia/Brisbane) for display
        if (config('database.default') === 'pgsql') {
            \DB::connection()->getPdo()->exec("SET timezone TO 'UTC'");
        }
        
        // Register middleware
        app('router')->aliasMiddleware('secure.headers', SecureHeaders::class);
    }
}
