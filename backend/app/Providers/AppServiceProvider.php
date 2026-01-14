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
            try {
                \DB::connection()->getPdo()->exec("SET timezone TO 'UTC'");
            } catch (\Throwable $e) {
                \Log::warning('Failed to set database timezone to UTC', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Register middleware
        app('router')->aliasMiddleware('secure.headers', SecureHeaders::class);

        // Register polymorphic morph map
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'User' => \App\Models\User::class,
            'Integration' => \App\Models\Integration::class,
            'Customer' => \App\Models\Customer::class,
            'Company' => \App\Models\Company::class,
            'License' => \App\Models\License::class,
            'ApiKey' => \App\Models\ApiKey::class,
        ]);
    }
}
