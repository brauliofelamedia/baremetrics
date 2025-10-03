<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLBasicConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-basic-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar conexión básica con GoHighLevel';

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
        $this->info('🔍 PRUEBA BÁSICA DE CONEXIÓN CON GOHIGHLEVEL');
        $this->info('============================================');
        $this->newLine();

        try {
            // Paso 1: Verificar configuración
            $this->info('📋 PASO 1: Verificando configuración...');
            $this->checkConfiguration();
            
            // Paso 2: Probar llamada simple
            $this->info('🌐 PASO 2: Probando llamada simple a la API...');
            $this->testSimpleCall();
            
            // Paso 3: Probar obtención de contactos
            $this->info('👥 PASO 3: Probando obtención de contactos...');
            $this->testGetContacts();
            
            $this->info('✅ Prueba básica completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            $this->line('Trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Verificar configuración
     */
    private function checkConfiguration()
    {
        $config = [
            'client_id' => config('services.gohighlevel.client_id'),
            'client_secret' => config('services.gohighlevel.client_secret'),
            'location_id' => config('services.gohighlevel.location_id'),
            'access_token' => config('services.gohighlevel.access_token'),
        ];
        
        $allConfigured = true;
        
        foreach ($config as $key => $value) {
            if ($value) {
                $this->line("✅ {$key}: Configurado");
            } else {
                $this->line("❌ {$key}: No configurado");
                $allConfigured = false;
            }
        }
        
        if (!$allConfigured) {
            $this->error('❌ Configuración incompleta. Verifica las variables de entorno.');
            throw new \Exception('Configuración incompleta');
        }
        
        $this->info('✅ Configuración OK');
        $this->newLine();
    }

    /**
     * Probar llamada simple
     */
    private function testSimpleCall()
    {
        try {
            // Intentar obtener custom fields (llamada simple)
            $response = $this->ghlService->getCustomFields();
            
            if ($response) {
                $this->info('✅ Llamada simple exitosa');
                $this->line('• La API responde correctamente');
            } else {
                $this->warn('⚠️  Llamada simple sin respuesta');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error en llamada simple: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), '401') !== false) {
                $this->line('💡 Token expirado. Ejecuta: php artisan ghl:refresh-token');
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->line('💡 Token sin permisos. Verifica la configuración');
            } elseif (strpos($e->getMessage(), '404') !== false) {
                $this->line('💡 Ubicación no encontrada. Verifica GHL_LOCATION_ID');
            }
            
            throw $e;
        }
        
        $this->newLine();
    }

    /**
     * Probar obtención de contactos
     */
    private function testGetContacts()
    {
        try {
            // Intentar obtener 1 contacto
            $this->line('• Intentando obtener 1 contacto...');
            $response = $this->ghlService->getContacts('', 1, 1);
            
            if ($response && isset($response['contacts'])) {
                $contacts = $response['contacts'];
                $this->info('✅ Obtención de contactos exitosa');
                $this->line("• Contactos obtenidos: " . count($contacts));
                
                if (!empty($contacts)) {
                    $contact = $contacts[0];
                    $this->line("• Primer contacto:");
                    $this->line("  - ID: " . ($contact['id'] ?? 'N/A'));
                    $this->line("  - Email: " . ($contact['email'] ?? 'N/A'));
                    $this->line("  - Nombre: " . ($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                    $this->line("  - Estado: " . ($contact['status'] ?? 'N/A'));
                    
                    // Verificar tags
                    $tags = $contact['tags'] ?? [];
                    $this->line("  - Tags: " . (empty($tags) ? 'Ninguno' : implode(', ', $tags)));
                }
                
                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                    $this->line("• Hay más páginas: " . ($hasMore ? 'SÍ' : 'NO'));
                }
                
            } else {
                $this->warn('⚠️  No se obtuvieron contactos');
                $this->line('• La respuesta está vacía o mal formada');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error obteniendo contactos: ' . $e->getMessage());
            throw $e;
        }
        
        $this->newLine();
    }
}
