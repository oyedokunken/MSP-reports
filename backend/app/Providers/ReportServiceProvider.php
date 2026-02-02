<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ReportRegistry;
use Illuminate\Support\ServiceProvider;

final class ReportServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ReportRegistry as a singleton
        $this->app->singleton(ReportRegistry::class, function ($app) {
            return new ReportRegistry();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Auto-discover reports when the application boots
        $registry = $this->app->make(ReportRegistry::class);
        $registry->discover();
    }
}
