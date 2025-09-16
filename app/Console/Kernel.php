<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Refrescar el token de GoHighLevel cada 20 horas para asegurarnos
        // que siempre esté fresco (tokens normalmente expiran en 24 horas)
        $schedule->command('gohighlevel:refresh-token')
                ->hourly()
                ->when(function () {
                    // Solo ejecutar si existe un token y está a punto de expirar (menos de 4 horas)
                    $config = \App\Models\Configuration::first();
                    return $config && 
                           $config->ghl_refresh_token && 
                           $config->ghl_token_expires_at && 
                           now()->addHours(4)->gte($config->ghl_token_expires_at);
                });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}