<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Models\Configuration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RefreshGHLToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:refresh-token 
                           {--force : Forzar renovación incluso si el token no ha expirado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresca el token de GoHighLevel manualmente';

    protected $ghlService;

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
        $force = $this->option('force');
        
        $this->info('🔄 Refrescando token de GoHighLevel...');
        
        try {
            // Verificar configuración (asegurarnos que la tabla exista)
            $config = null;
            try {
                if (Schema::hasTable('configurations')) {
                    $config = Configuration::first();
                }
            } catch (\Exception $e) {
                $config = null;
            }

            if (!$config) {
                $this->error('❌ No hay configuración de GoHighLevel en la base de datos');
                $this->warn('💡 Necesitas ejecutar el proceso de autorización inicial primero');
                $this->warn('💡 Ve a /admin/ghlevel/initial en tu navegador');
                return 1;
            }

            if (!$config->ghl_refresh_token) {
                $this->error('❌ No hay token de renovación disponible');
                $this->warn('💡 Necesitas ejecutar el proceso de autorización inicial nuevamente');
                $this->warn('💡 Ve a /admin/ghlevel/initial en tu navegador');
                return 1;
            }

            // Verificar si el token necesita renovación
            if (!$force && $config->ghl_token_expires_at) {
                $now = now();
                $expiresAt = $config->ghl_token_expires_at;
                
                if ($now->lt($expiresAt)) {
                    $minutesLeft = $now->diffInMinutes($expiresAt);
                    $this->warn("⚠️  El token aún es válido por {$minutesLeft} minutos");
                    
                    if (!$this->confirm('¿Deseas forzar la renovación del token?')) {
                        $this->info('Operación cancelada');
                        return 0;
                    }
                }
            }

            $this->info('🔑 Renovando token...');
            
            // Intentar renovar el token
            $newToken = $this->ghlService->refreshToken();
            
            if ($newToken) {
                $this->info('✅ Token renovado exitosamente');
                
                // Mostrar información del nuevo token (recargar si la tabla existe)
                try {
                    if (Schema::hasTable('configurations')) {
                        $config = Configuration::first(); // Recargar configuración
                    }
                } catch (\Exception $e) {
                    $config = null;
                }
                if ($config && $config->ghl_token_expires_at) {
                    $this->info("📅 Nuevo token expira: {$config->ghl_token_expires_at->format('Y-m-d H:i:s')}");
                }
                
                // Probar la conexión con el nuevo token
                $this->info('🧪 Probando conexión con el nuevo token...');
                try {
                    $response = $this->ghlService->getLocation();
                    if ($response) {
                        $this->info('✅ Conexión exitosa con el nuevo token');
                    } else {
                        $this->warn('⚠️  No se recibió respuesta de GoHighLevel');
                    }
                } catch (\Exception $e) {
                    $this->error('❌ Error probando conexión: ' . $e->getMessage());
                }
                
            } else {
                $this->error('❌ No se pudo renovar el token');
                return 1;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error renovando token: ' . $e->getMessage());
            Log::error('Error renovando token de GoHighLevel', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
