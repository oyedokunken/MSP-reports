<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Delivery\DeliveryManager;

class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeliveryManager::class, function ($app) {
            return new DeliveryManager();
        });
    }

    public function boot(): void
    {
        //
    }
}
