<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DebugRawAPIResponse extends Command
{
    protected $signature = 'baremetrics:debug-raw-response 
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}';
    
    protected $description = 'Debug: Muestra la respuesta cruda de la API de Baremetrics';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $sourceId = $this->option('source-id');

        $this->info("🔍 DEBUG: RESPUESTA CRUDA DE LA API");
        $this->info("===================================");
        $this->info("Source ID: {$sourceId}");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            $this->info("🔍 Obteniendo respuesta de la API...");
            
            // Obtener solo la primera página para ver la estructura
            $response = $this->baremetricsService->getCustomers($sourceId, '', 0);
            
            if (!$response) {
                $this->error("❌ No se pudo obtener respuesta de la API");
                return 1;
            }

            $this->info("✅ Respuesta obtenida exitosamente");
            $this->newLine();

            // Mostrar estructura de la respuesta
            $this->info("📋 ESTRUCTURA DE LA RESPUESTA:");
            $this->info("==============================");
            
            if (is_array($response)) {
                $this->info("• Tipo: Array");
                $this->info("• Claves principales: " . implode(', ', array_keys($response)));
                
                if (isset($response['customers'])) {
                    $this->info("• Número de customers: " . count($response['customers']));
                    
                    if (count($response['customers']) > 0) {
                        $firstCustomer = $response['customers'][0];
                        $this->info("• Claves del primer customer: " . implode(', ', array_keys($firstCustomer)));
                        
                        if (isset($firstCustomer['properties'])) {
                            $this->info("• Properties del primer customer: " . count($firstCustomer['properties']));
                            
                            if (count($firstCustomer['properties']) > 0) {
                                $firstProperty = $firstCustomer['properties'][0];
                                $this->info("• Claves del primer property: " . implode(', ', array_keys($firstProperty)));
                                $this->info("• Primer property:");
                                $this->info("  - field_id: " . ($firstProperty['field_id'] ?? 'N/A'));
                                $this->info("  - name: " . ($firstProperty['name'] ?? 'N/A'));
                                $this->info("  - value: " . ($firstProperty['value'] ?? 'N/A'));
                            } else {
                                $this->warn("⚠️ El primer customer no tiene properties");
                            }
                        } else {
                            $this->warn("⚠️ El primer customer no tiene la clave 'properties'");
                        }
                    }
                } else {
                    $this->warn("⚠️ No hay clave 'customers' en la respuesta");
                }
                
                if (isset($response['meta'])) {
                    $this->info("• Meta información disponible: " . implode(', ', array_keys($response['meta'])));
                }
            } else {
                $this->info("• Tipo: " . gettype($response));
            }

            // Buscar usuarios con custom fields en toda la respuesta
            $this->newLine();
            $this->info("🔍 BUSCANDO USUARIOS CON CUSTOM FIELDS EN LA RESPUESTA:");
            $this->info("======================================================");
            
            $usersWithCustomFields = 0;
            $usersWithMigrateField = 0;
            
            if (isset($response['customers'])) {
                foreach ($response['customers'] as $customer) {
                    $properties = $customer['properties'] ?? [];
                    
                    if (!empty($properties)) {
                        $usersWithCustomFields++;
                        
                        // Buscar el campo específico
                        foreach ($properties as $field) {
                            $fieldId = $field['field_id'] ?? null;
                            $fieldName = $field['name'] ?? null;
                            $fieldValue = $field['value'] ?? null;
                            
                            if (($fieldId === '844539743') || ($fieldName === 'GHL: Migrate GHL')) {
                                $usersWithMigrateField++;
                                $this->info("✅ Usuario con campo GHL: Migrate GHL encontrado:");
                                $this->info("   • Email: " . ($customer['email'] ?? 'N/A'));
                                $this->info("   • Customer ID: " . ($customer['oid'] ?? 'N/A'));
                                $this->info("   • Valor: '{$fieldValue}'");
                                break;
                            }
                        }
                    }
                }
            }
            
            $this->info("📊 Resumen:");
            $this->info("   • Usuarios con custom fields: {$usersWithCustomFields}");
            $this->info("   • Usuarios con campo GHL: Migrate GHL: {$usersWithMigrateField}");

        } catch (\Exception $e) {
            $this->error("❌ Error durante el debug: " . $e->getMessage());
            Log::error('Error en debug de respuesta cruda', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
