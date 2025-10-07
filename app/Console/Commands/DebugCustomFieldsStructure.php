<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DebugCustomFieldsStructure extends Command
{
    protected $signature = 'baremetrics:debug-custom-fields 
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}
                           {--limit=5 : Número de usuarios a mostrar}';
    
    protected $description = 'Debug: Muestra la estructura de custom_fields de los usuarios para investigar problemas';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $sourceId = $this->option('source-id');
        $limit = (int) $this->option('limit');

        $this->info("🔍 DEBUG: ESTRUCTURA DE CUSTOM FIELDS");
        $this->info("=====================================");
        $this->info("Source ID: {$sourceId}");
        $this->info("Límite: {$limit} usuarios");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            $this->info("🔍 Obteniendo usuarios de Baremetrics...");
            
            // Obtener usuarios con paginación completa
            $this->info("🔍 Obteniendo usuarios de Baremetrics (con paginación)...");
            
            $allCustomers = [];
            $page = 0;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomers($sourceId, '', $page);
                
                if (!$response) {
                    $this->error("❌ No se pudo obtener respuesta de la página {$page}");
                    break;
                }
                
                $customers = [];
                if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }
                
                $allCustomers = array_merge($allCustomers, $customers);
                
                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }
                
                $this->info("   Página {$page}: " . count($customers) . " usuarios obtenidos");
                $page++;
                
                if ($page > 10) { // Límite de seguridad
                    $this->warn("⚠️ Límite de páginas alcanzado (10)");
                    break;
                }
                
                usleep(100000); // Pausa entre requests
            }

            $this->info("📊 Total de usuarios obtenidos: " . count($allCustomers));
            $this->newLine();

            // Mostrar estructura de usuarios CON custom fields
            $count = 0;
            $usersWithCustomFields = 0;
            foreach ($allCustomers as $customer) {
                $customFields = $customer['custom_fields'] ?? [];
                if (empty($customFields)) continue; // Saltar usuarios sin custom fields
                
                $usersWithCustomFields++;
                if ($count >= $limit) break;
                
                $customerId = $customer['oid'] ?? 'N/A';
                $email = $customer['email'] ?? 'N/A';
                $name = $customer['name'] ?? 'N/A';
                
                $this->info("👤 Usuario CON Custom Fields #" . ($count + 1) . ":");
                $this->info("   • Customer ID: {$customerId}");
                $this->info("   • Email: {$email}");
                $this->info("   • Nombre: {$name}");
                
                // Mostrar custom_fields
                $this->info("   • Custom Fields (" . count($customFields) . "):");
                
                foreach ($customFields as $index => $field) {
                    $fieldId = $field['field_id'] ?? 'N/A';
                    $fieldName = $field['name'] ?? 'N/A';
                    $fieldValue = $field['value'] ?? 'N/A';
                    $fieldType = $field['type'] ?? 'N/A';
                    
                    $this->info("     {$index}:");
                    $this->info("       - ID: {$fieldId}");
                    $this->info("       - Name: {$fieldName}");
                    $this->info("       - Value: {$fieldValue}");
                    $this->info("       - Type: {$fieldType}");
                    
                    // Destacar si es el campo que buscamos
                    if ($fieldId === '844539743' || $fieldName === 'GHL: Migrate GHL') {
                        $this->info("       ⭐ ESTE ES EL CAMPO QUE BUSCAMOS!");
                    }
                }
                
                $this->newLine();
                $count++;
            }
            
            $this->info("📊 Usuarios CON custom fields encontrados: {$usersWithCustomFields}");
            $this->newLine();

            // Buscar específicamente usuarios con el campo GHL: Migrate GHL
            $this->info("🔍 BUSCANDO USUARIOS CON CAMPO 'GHL: Migrate GHL'...");
            $this->info("==================================================");
            
            $usersWithMigrateField = [];
            foreach ($allCustomers as $customer) {
                $customFields = $customer['custom_fields'] ?? [];
                
                foreach ($customFields as $field) {
                    $fieldId = $field['field_id'] ?? null;
                    $fieldName = $field['name'] ?? null;
                    $fieldValue = $field['value'] ?? null;
                    
                    // Buscar por ID o por nombre
                    if (($fieldId === '844539743') || ($fieldName === 'GHL: Migrate GHL')) {
                        $usersWithMigrateField[] = [
                            'customer' => $customer,
                            'field' => $field
                        ];
                        break;
                    }
                }
            }
            
            $this->info("📊 Usuarios con campo 'GHL: Migrate GHL': " . count($usersWithMigrateField));
            
            if (count($usersWithMigrateField) > 0) {
                $this->info("📋 DETALLES DE USUARIOS CON EL CAMPO:");
                foreach ($usersWithMigrateField as $index => $userData) {
                    $customer = $userData['customer'];
                    $field = $userData['field'];
                    
                    $customerId = $customer['oid'] ?? 'N/A';
                    $email = $customer['email'] ?? 'N/A';
                    $fieldValue = $field['value'] ?? 'N/A';
                    
                    $this->info("   " . ($index + 1) . ". {$email} (ID: {$customerId})");
                    $this->info("      Valor del campo: '{$fieldValue}'");
                    
                    // Verificar diferentes valores posibles
                    if ($fieldValue === 'true') {
                        $this->info("      ✅ Valor es 'true'");
                    } elseif ($fieldValue === 'Yes') {
                        $this->info("      ⚠️ Valor es 'Yes' (no 'true')");
                    } elseif ($fieldValue === '1') {
                        $this->info("      ⚠️ Valor es '1' (no 'true')");
                    } else {
                        $this->info("      ❓ Valor inesperado: '{$fieldValue}'");
                    }
                }
            } else {
                $this->warn("⚠️ No se encontraron usuarios con el campo 'GHL: Migrate GHL'");
            }

            // Mostrar todos los valores únicos del campo
            $this->newLine();
            $this->info("🔍 VALORES ÚNICOS DEL CAMPO 'GHL: Migrate GHL':");
            $this->info("===============================================");
            
            $uniqueValues = [];
            foreach ($allCustomers as $customer) {
                $customFields = $customer['custom_fields'] ?? [];
                
                foreach ($customFields as $field) {
                    $fieldId = $field['field_id'] ?? null;
                    $fieldName = $field['name'] ?? null;
                    $fieldValue = $field['value'] ?? null;
                    
                    if (($fieldId === '844539743') || ($fieldName === 'GHL: Migrate GHL')) {
                        if (!in_array($fieldValue, $uniqueValues)) {
                            $uniqueValues[] = $fieldValue;
                        }
                    }
                }
            }
            
            if (empty($uniqueValues)) {
                $this->warn("⚠️ No se encontraron valores para el campo");
            } else {
                foreach ($uniqueValues as $value) {
                    $this->info("   • '{$value}'");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante el debug: " . $e->getMessage());
            Log::error('Error en debug de custom fields', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
