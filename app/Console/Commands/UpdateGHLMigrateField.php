<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class UpdateGHLMigrateField extends Command
{
    protected $signature = 'baremetrics:update-migrate-field 
                           {customer_id : ID del cliente en Baremetrics (ej: cust_68e55c311a2b9)}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}
                           {--value=true : Valor a establecer (true/false)}';
    
    protected $description = 'Actualiza específicamente el campo "GHL: Migrate GHL" usando el ID del campo personalizado';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $customerId = $this->argument('customer_id');
        $dryRun = $this->option('dry-run');
        $value = $this->option('value');

        $this->info("🔧 ACTUALIZACIÓN DE CAMPO GHL: MIGRATE GHL");
        $this->info("=========================================");
        $this->info("ID del cliente: {$customerId}");
        $this->info("Valor a establecer: {$value}");
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Buscar cliente por ID en Baremetrics
            $customer = $this->findCustomerById($customerId, $sourceId);
            if (!$customer) {
                $this->error("❌ No se encontró el cliente con ID: {$customerId}");
                return 1;
            }

            $this->info("✅ Cliente encontrado: {$customer['oid']}");
            $this->info("📧 Email: " . ($customer['email'] ?? 'No disponible'));
            $this->info("👤 Nombre: " . ($customer['name'] ?? 'No disponible'));

            // 2. Verificar estado actual del campo
            $currentValue = $this->getCurrentMigrateValue($customer);
            $this->info("📋 Valor actual de 'GHL: Migrate GHL': " . ($currentValue ?: 'No establecido'));

            // 3. Actualizar el campo
            if ($dryRun) {
                $this->info("🔍 DRY RUN: Se actualizaría 'GHL: Migrate GHL' a: {$value}");
                return 0;
            }

            $this->info("🔄 Actualizando campo 'GHL: Migrate GHL'...");
            
            // Usar el ID específico del campo: 844539743
            $migrateData = ['GHL: Migrate GHL' => $value];
            $updateResult = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $migrateData);
            
            if ($updateResult) {
                $this->info("✅ Campo 'GHL: Migrate GHL' actualizado exitosamente a: {$value}");
                
                // Verificar el cambio
                $newCustomer = $this->findCustomerById($customerId, $sourceId);
                $newValue = $this->getCurrentMigrateValue($newCustomer);
                $this->info("📋 Nuevo valor verificado: " . ($newValue ?: 'No establecido'));
                
            } else {
                $this->error("❌ Error actualizando campo 'GHL: Migrate GHL'");
                return 1;
            }

            $this->newLine();
            $this->info("🎉 ¡Actualización completada para el usuario {$customerId}!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la actualización: " . $e->getMessage());
            Log::error('Error actualizando campo GHL: Migrate GHL', [
                'customer_id' => $customerId,
                'value' => $value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Buscar cliente por ID en Baremetrics
     */
    private function findCustomerById(string $customerId, string $sourceId): ?array
    {
        try {
            // Buscar en la lista de clientes
            $customers = $this->baremetricsService->getCustomers($sourceId);
            
            if (!$customers || !isset($customers['customers'])) {
                return null;
            }

            foreach ($customers['customers'] as $customer) {
                if ($customer['oid'] === $customerId) {
                    return $customer;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error buscando cliente por ID', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener el valor actual del campo GHL: Migrate GHL
     */
    private function getCurrentMigrateValue(array $customer): ?string
    {
        // Buscar en properties (nuevo formato)
        if (isset($customer['properties']) && is_array($customer['properties'])) {
            foreach ($customer['properties'] as $field) {
                if (isset($field['field_id']) && $field['field_id'] === '844539743') {
                    return $field['value'] ?? null;
                }
            }
        }

        // Buscar en custom_fields (formato alternativo)
        if (isset($customer['custom_fields']) && is_array($customer['custom_fields'])) {
            foreach ($customer['custom_fields'] as $field) {
                if (isset($field['field_id']) && $field['field_id'] === '844539743') {
                    return $field['value'] ?? null;
                }
                if (isset($field['name']) && $field['name'] === 'GHL: Migrate GHL') {
                    return $field['value'] ?? null;
                }
            }
        }

        // Buscar en attributes (formato alternativo)
        if (isset($customer['attributes']) && is_array($customer['attributes'])) {
            foreach ($customer['attributes'] as $attribute) {
                if (isset($attribute['name']) && $attribute['name'] === 'GHL: Migrate GHL') {
                    return $attribute['value'] ?? null;
                }
            }
        }

        return null;
    }
}
