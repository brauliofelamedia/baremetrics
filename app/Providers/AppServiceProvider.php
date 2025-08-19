<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\StripeService;
use App\Services\BaremetricsService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeService::class, function ($app) {
            return new StripeService();
        });

        $this->app->singleton(BaremetricsService::class, function ($app) {
            return new BaremetricsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
