<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Configuration;
use Illuminate\Support\Facades\Schema;

class ScheduleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Refrescar el token de GoHighLevel cada hora si está cerca de expirar
            $schedule->command('gohighlevel:refresh-token')
                ->hourly()
                ->when(function () {
                    // Solo ejecutar si existe un token y está a punto de expirar (menos de 4 horas)
                    // Evitar consultar la DB si la tabla no existe (migrations en curso)
                    if (!Schema::hasTable('configurations')) {
                        return false;
                    }

                    try {
                        $config = Configuration::first();
                    } catch (\Exception $e) {
                        return false;
                    }

                    return $config && 
                           $config->ghl_refresh_token && 
                           $config->ghl_token_expires_at && 
                           now()->addHours(4)->gte($config->ghl_token_expires_at);
                });
        });
    }
}