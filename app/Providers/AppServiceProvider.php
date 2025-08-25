<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\StripeService;
use App\Services\BaremetricsService;
use App\Services\SystemService;

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

        $this->app->singleton(SystemService::class, function ($app) {
            return new SystemService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share system configuration with all views
        View::composer('*', function ($view) {
            if (!$view->offsetExists('systemConfig')) {
                $systemService = app(SystemService::class);
                $view->with('systemConfig', $systemService->getConfiguration());
            }
        });
    }
}
