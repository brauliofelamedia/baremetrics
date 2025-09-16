<?php

namespace App\Console\Commands;

use App\Models\Configuration;
use App\Services\GoHighLevelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshGoHighLevelToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gohighlevel:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresca el token de GoHighLevel';

    /**
     * El servicio de GoHighLevel.
     *
     * @var GoHighLevelService
     */
    protected $ghlService;

    /**
     * Constructor.
     */
    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refrescando token de GoHighLevel...');
        
        try {
            $config = Configuration::first();
            
            if (!$config) {
                $this->error('No hay configuración disponible en la base de datos.');
                return 1;
            }
            
            if (!$config->ghl_refresh_token) {
                $this->error('No hay refresh token disponible. Debes volver a autenticarte en GoHighLevel.');
                return 1;
            }
            
            $token = $this->ghlService->refreshToken();
            
            $this->info('Token refrescado correctamente.');
            $this->info('Nuevo token expira en: ' . $config->refresh()->ghl_token_expires_at);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error al refrescar el token: ' . $e->getMessage());
            Log::error('Error al refrescar el token de GoHighLevel desde el comando', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
