<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class DiagnoseGHLConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:diagnose-connection 
                           {--test-api : Probar conexión a la API}
                           {--test-token : Verificar token de acceso}
                           {--test-location : Verificar configuración de ubicación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosticar problemas de conexión con GoHighLevel';

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
        $testApi = $this->option('test-api');
        $testToken = $this->option('test-token');
        $testLocation = $this->option('test-location');

        $this->info('🔍 DIAGNÓSTICO DE CONEXIÓN CON GOHIGHLEVEL');
        $this->info('==========================================');
        $this->newLine();

        try {
            // Verificar configuración básica
            $this->checkBasicConfiguration();
            
            // Probar token si se solicita
            if ($testToken) {
                $this->testToken();
            }
            
            // Probar ubicación si se solicita
            if ($testLocation) {
                $this->testLocation();
            }
            
            // Probar API si se solicita
            if ($testApi) {
                $this->testAPI();
            }
            
            $this->info('✅ Diagnóstico completado');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante el diagnóstico: " . $e->getMessage());
            Log::error('Error en diagnóstico de conexión GHL', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * Verificar configuración básica
     */
    private function checkBasicConfiguration()
    {
        $this->info('🔧 VERIFICANDO CONFIGURACIÓN BÁSICA:');
        $this->info('====================================');
        
        // Verificar variables de entorno
        $ghlClientId = config('services.gohighlevel.client_id');
        $ghlClientSecret = config('services.gohighlevel.client_secret');
        $ghlLocationId = config('services.gohighlevel.location_id');
        $ghlToken = config('services.gohighlevel.access_token');
        
        $this->line("• Client ID: " . ($ghlClientId ? '✅ Configurado' : '❌ No configurado'));
        $this->line("• Client Secret: " . ($ghlClientSecret ? '✅ Configurado' : '❌ No configurado'));
        $this->line("• Location ID: " . ($ghlLocationId ? "✅ {$ghlLocationId}" : '❌ No configurado'));
        $this->line("• Access Token: " . ($ghlToken ? '✅ Configurado' : '❌ No configurado'));
        
        if (!$ghlClientId || !$ghlClientSecret || !$ghlLocationId || !$ghlToken) {
            $this->error('❌ Configuración incompleta. Verifica las variables de entorno.');
            $this->line('Variables necesarias:');
            $this->line('• GHL_CLIENT_ID');
            $this->line('• GHL_CLIENT_SECRET');
            $this->line('• GHL_LOCATION_ID');
            $this->line('• GHL_ACCESS_TOKEN');
            return;
        }
        
        $this->info('✅ Configuración básica OK');
        $this->newLine();
    }

    /**
     * Probar token
     */
    private function testToken()
    {
        $this->info('🔑 PROBANDO TOKEN DE ACCESO:');
        $this->info('============================');
        
        try {
            // Intentar hacer una llamada simple a la API
            $response = $this->ghlService->getCustomFields();
            
            if ($response) {
                $this->info('✅ Token válido - API responde correctamente');
            } else {
                $this->warn('⚠️  Token puede estar expirado - API no responde');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error con el token: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), '401') !== false) {
                $this->line('💡 El token está expirado. Ejecuta: php artisan ghl:refresh-token');
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->line('💡 El token no tiene permisos suficientes');
            }
        }
        
        $this->newLine();
    }

    /**
     * Probar ubicación
     */
    private function testLocation()
    {
        $this->info('📍 PROBANDO CONFIGURACIÓN DE UBICACIÓN:');
        $this->info('=======================================');
        
        try {
            // Intentar obtener contactos con límite muy pequeño
            $response = $this->ghlService->getContacts('', 1, 1);
            
            if ($response && isset($response['contacts'])) {
                $this->info('✅ Ubicación válida - Se pueden obtener contactos');
                $this->line("• Contactos en respuesta: " . count($response['contacts']));
                
                if (!empty($response['contacts'])) {
                    $contact = $response['contacts'][0];
                    $this->line("• Primer contacto ID: " . ($contact['id'] ?? 'N/A'));
                    $this->line("• Primer contacto email: " . ($contact['email'] ?? 'N/A'));
                }
            } else {
                $this->warn('⚠️  Ubicación puede ser incorrecta - No se obtienen contactos');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error con la ubicación: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), '404') !== false) {
                $this->line('💡 La ubicación no existe. Verifica GHL_LOCATION_ID');
            }
        }
        
        $this->newLine();
    }

    /**
     * Probar API
     */
    private function testAPI()
    {
        $this->info('🌐 PROBANDO CONEXIÓN A LA API:');
        $this->info('===============================');
        
        try {
            // Probar con diferentes límites
            $limits = [1, 10, 100];
            
            foreach ($limits as $limit) {
                $this->line("• Probando con límite {$limit}...");
                
                $startTime = microtime(true);
                $response = $this->ghlService->getContacts('', 1, $limit);
                $endTime = microtime(true);
                
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                if ($response && isset($response['contacts'])) {
                    $count = count($response['contacts']);
                    $this->line("  ✅ OK - {$count} contactos en {$duration}ms");
                    
                    // Verificar paginación
                    if (isset($response['meta']['pagination'])) {
                        $pagination = $response['meta']['pagination'];
                        $hasMore = $pagination['has_more'] ?? false;
                        $this->line("  📄 Hay más páginas: " . ($hasMore ? 'SÍ' : 'NO'));
                    }
                } else {
                    $this->line("  ❌ Error - No se obtuvieron contactos");
                }
            }
            
            $this->info('✅ Pruebas de API completadas');
            
        } catch (\Exception $e) {
            $this->error('❌ Error en pruebas de API: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), 'timeout') !== false) {
                $this->line('💡 Problema de timeout - La API es muy lenta');
            } elseif (strpos($e->getMessage(), 'connection') !== false) {
                $this->line('💡 Problema de conexión - Verifica tu internet');
            }
        }
        
        $this->newLine();
    }
}