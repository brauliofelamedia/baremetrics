<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Models\Configuration;
use Illuminate\Support\Facades\Log;

class DiagnoseGHLConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:diagnose-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica problemas de conexión con GoHighLevel';

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
        $this->info('🔍 Diagnostico de conexión con GoHighLevel');
        $this->newLine();

        $allGood = true;

        // 1. Verificar configuración básica
        $allGood &= $this->checkBasicConfig();

        // 2. Verificar configuración en base de datos
        $allGood &= $this->checkDatabaseConfig();

        // 3. Verificar token y expiración
        $allGood &= $this->checkTokenStatus();

        // 4. Probar conexión real
        $allGood &= $this->testConnection();

        $this->newLine();
        
        if ($allGood) {
            $this->info('✅ ¡La conexión con GoHighLevel está funcionando correctamente!');
        } else {
            $this->error('❌ Se encontraron problemas en la conexión con GoHighLevel.');
            $this->showSolutions();
        }

        return $allGood ? 0 : 1;
    }

    /**
     * Verifica la configuración básica del archivo .env
     */
    private function checkBasicConfig()
    {
        $this->info('📋 1. Verificando configuración básica...');
        
        $configs = [
            'GHL_CLIENT_ID' => config('services.gohighlevel.client_id'),
            'GHL_CLIENT_SECRET' => config('services.gohighlevel.client_secret'),
            'GHL_LOCATION' => config('services.gohighlevel.location'),
            'GHL_AUTORIZATION_URL' => config('services.gohighlevel.authorization_url'),
            'GHL_SCOPES' => config('services.gohighlevel.scopes'),
        ];

        $allGood = true;
        foreach ($configs as $key => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$key}: No configurado");
                $allGood = false;
            } else {
                $this->info("  ✅ {$key}: Configurado");
            }
        }

        return $allGood;
    }

    /**
     * Verifica la configuración en la base de datos
     */
    private function checkDatabaseConfig()
    {
        $this->info('🗄️  2. Verificando configuración en base de datos...');
        
        $config = Configuration::first();
        
        if (!$config) {
            $this->error('  ❌ No hay registro de configuración en la base de datos');
            $this->warn('  💡 Necesitas ejecutar el proceso de autorización inicial');
            return false;
        }

        $this->info('  ✅ Registro de configuración encontrado');

        // Verificar campos importantes
        $fields = [
            'ghl_token' => 'Token de acceso',
            'ghl_refresh_token' => 'Token de renovación',
            'ghl_location' => 'ID de ubicación',
            'ghl_company' => 'ID de compañía'
        ];

        $allGood = true;
        foreach ($fields as $field => $description) {
            if (empty($config->$field)) {
                $this->warn("  ⚠️  {$description}: No configurado");
                if ($field === 'ghl_token') {
                    $allGood = false;
                }
            } else {
                $this->info("  ✅ {$description}: Configurado");
            }
        }

        return $allGood;
    }

    /**
     * Verifica el estado del token
     */
    private function checkTokenStatus()
    {
        $this->info('🔑 3. Verificando estado del token...');
        
        $config = Configuration::first();
        
        if (!$config || !$config->ghl_token) {
            $this->error('  ❌ No hay token de acceso disponible');
            return false;
        }

        $this->info('  ✅ Token de acceso disponible');

        // Verificar expiración
        if ($config->ghl_token_expires_at) {
            $now = now();
            $expiresAt = $config->ghl_token_expires_at;
            
            if ($now->gte($expiresAt)) {
                $this->error('  ❌ Token expirado');
                $this->info("  📅 Expiró: {$expiresAt->format('Y-m-d H:i:s')}");
                $this->info("  📅 Ahora: {$now->format('Y-m-d H:i:s')}");
                return false;
            } else {
                $this->info('  ✅ Token válido');
                $this->info("  📅 Expira: {$expiresAt->format('Y-m-d H:i:s')}");
                
                $minutesLeft = $now->diffInMinutes($expiresAt);
                if ($minutesLeft < 60) {
                    $this->warn("  ⚠️  Token expira en {$minutesLeft} minutos");
                }
            }
        } else {
            $this->warn('  ⚠️  No hay fecha de expiración configurada');
        }

        return true;
    }

    /**
     * Prueba la conexión real con GoHighLevel
     */
    private function testConnection()
    {
        $this->info('🌐 4. Probando conexión con GoHighLevel...');
        
        try {
            $this->info('  🔍 Probando endpoint de ubicaciones...');
            $response = $this->ghlService->getLocation();
            
            if ($response) {
                $this->info('  ✅ Conexión exitosa con GoHighLevel');
                
                // Intentar obtener datos de ubicación
                $locationData = json_decode($response, true);
                if ($locationData && isset($locationData['locations'])) {
                    $this->info('  📍 Ubicaciones encontradas: ' . count($locationData['locations']));
                }
                
                return true;
            } else {
                $this->error('  ❌ No se recibió respuesta de GoHighLevel');
                return false;
            }
            
        } catch (\Exception $e) {
            $this->error('  ❌ Error de conexión: ' . $e->getMessage());
            
            // Analizar el tipo de error
            if (strpos($e->getMessage(), '401') !== false) {
                $this->warn('  💡 Error 401: Token inválido o expirado');
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->warn('  💡 Error 403: Permisos insuficientes');
            } elseif (strpos($e->getMessage(), '404') !== false) {
                $this->warn('  💡 Error 404: Endpoint no encontrado');
            } elseif (strpos($e->getMessage(), '500') !== false) {
                $this->warn('  💡 Error 500: Error interno del servidor de GoHighLevel');
            }
            
            return false;
        }
    }

    /**
     * Muestra soluciones para los problemas encontrados
     */
    private function showSolutions()
    {
        $this->newLine();
        $this->info('🔧 SOLUCIONES RECOMENDADAS:');
        $this->info('==========================');
        
        $this->line('1. **Verificar configuración en .env**:');
        $this->line('   - Asegúrate de que todas las variables GHL_* estén configuradas');
        $this->line('   - Verifica que los valores sean correctos');
        
        $this->line('');
        $this->line('2. **Renovar autorización**:');
        $this->line('   - Ve a /admin/ghlevel/initial en tu navegador');
        $this->line('   - Completa el proceso de autorización');
        $this->line('   - Esto generará un nuevo token');
        
        $this->line('');
        $this->line('3. **Verificar permisos en GoHighLevel**:');
        $this->line('   - Asegúrate de que la aplicación tenga los permisos necesarios');
        $this->line('   - Verifica que la ubicación esté correctamente configurada');
        
        $this->line('');
        $this->line('4. **Revisar logs**:');
        $this->line('   - Revisa storage/logs/laravel.log para más detalles');
        $this->line('   - Busca errores relacionados con GoHighLevel');
        
        $this->line('');
        $this->line('5. **Probar manualmente**:');
        $this->line('   - Ejecuta: php artisan ghl:check-config');
        $this->line('   - Ejecuta: php artisan ghl:list-contacts --limit=1');
    }
}
