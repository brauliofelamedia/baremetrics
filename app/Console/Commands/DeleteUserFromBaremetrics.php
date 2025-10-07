<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DeleteUserFromBaremetrics extends Command
{
    protected $signature = 'baremetrics:delete-user 
                           {customer_id : ID del customer a eliminar (ej: cust_68e4e0ffdd60b)}
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}
                           {--confirm : Confirmar eliminación sin preguntar}';
    
    protected $description = 'Elimina completamente un usuario de Baremetrics (suscripciones + customer) SIN re-importar';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $customerId = $this->argument('customer_id');
        $sourceId = $this->option('source-id');
        $confirm = $this->option('confirm');

        $this->info("🗑️ ELIMINACIÓN COMPLETA DE USUARIO DE BAREMETRICS");
        $this->info("=================================================");
        $this->info("Customer ID: {$customerId}");
        $this->info("Source ID: {$sourceId}");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            // 1. Verificar que el customer existe
            $this->info("🔍 Verificando existencia del customer...");
            $customers = $this->baremetricsService->getCustomers($sourceId);
            
            $targetCustomer = null;
            if ($customers && isset($customers['customers'])) {
                foreach ($customers['customers'] as $customer) {
                    if ($customer['oid'] === $customerId) {
                        $targetCustomer = $customer;
                        break;
                    }
                }
            }

            if (!$targetCustomer) {
                $this->error("❌ Customer no encontrado: {$customerId}");
                return 1;
            }

            $this->info("✅ Customer encontrado:");
            $this->info("   • Email: " . ($targetCustomer['email'] ?? 'N/A'));
            $this->info("   • Nombre: " . ($targetCustomer['name'] ?? 'N/A'));
            $this->info("   • ID: " . $targetCustomer['oid']);
            $this->newLine();

            // 2. Buscar suscripciones del customer
            $this->info("🔍 Buscando suscripciones del customer...");
            $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
            $customerSubscriptions = [];
            
            if ($subscriptions && isset($subscriptions['subscriptions'])) {
                foreach ($subscriptions['subscriptions'] as $subscription) {
                    $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                             $subscription['customer']['oid'] ?? 
                                             $subscription['customerOid'] ?? 
                                             null;
                    
                    if ($subscriptionCustomerOid === $customerId) {
                        $customerSubscriptions[] = $subscription;
                    }
                }
            }

            $this->info("📋 Suscripciones encontradas: " . count($customerSubscriptions));
            foreach ($customerSubscriptions as $subscription) {
                $this->info("   • ID: " . ($subscription['oid'] ?? 'N/A'));
                $this->info("     Plan: " . ($subscription['plan']['name'] ?? 'N/A'));
                $this->info("     Estado: " . ($subscription['status'] ?? 'N/A'));
            }
            $this->newLine();

            // 3. Confirmar eliminación
            if (!$confirm) {
                $this->warn("⚠️  ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE:");
                $this->warn("   • " . count($customerSubscriptions) . " suscripción(es)");
                $this->warn("   • 1 customer completo");
                $this->warn("   • TODOS los datos asociados");
                $this->warn("   • NO se re-importará el usuario");
                $this->newLine();

                if (!$this->confirm('¿Estás seguro de que quieres continuar?')) {
                    $this->info("❌ Operación cancelada por el usuario");
                    return 0;
                }
            }

            // 4. Eliminar suscripciones
            $this->info("🗑️ Eliminando suscripciones...");
            $deletedSubscriptions = 0;
            $failedSubscriptions = 0;

            foreach ($customerSubscriptions as $subscription) {
                $subscriptionOid = $subscription['oid'];
                $this->info("   Eliminando suscripción: {$subscriptionOid}");
                
                $deleteResult = $this->baremetricsService->deleteSubscription($sourceId, $subscriptionOid);
                if ($deleteResult) {
                    $this->info("   ✅ Suscripción eliminada: {$subscriptionOid}");
                    $deletedSubscriptions++;
                } else {
                    $this->error("   ❌ Error eliminando suscripción: {$subscriptionOid}");
                    $failedSubscriptions++;
                }
            }

            $this->newLine();
            $this->info("📊 Resumen de suscripciones:");
            $this->info("   • Eliminadas: {$deletedSubscriptions}");
            $this->info("   • Fallidas: {$failedSubscriptions}");

            // 5. Eliminar customer
            $this->newLine();
            $this->info("🗑️ Eliminando customer...");
            $this->info("   Eliminando customer: {$customerId}");
            
            $deleteCustomerResult = $this->baremetricsService->deleteCustomer($sourceId, $customerId);
            if ($deleteCustomerResult) {
                $this->info("   ✅ Customer eliminado: {$customerId}");
            } else {
                $this->error("   ❌ Error eliminando customer: {$customerId}");
                return 1;
            }

            // 6. Verificar eliminación
            $this->newLine();
            $this->info("🔍 Verificando eliminación...");
            
            $customersAfter = $this->baremetricsService->getCustomers($sourceId);
            $customerStillExists = false;
            
            if ($customersAfter && isset($customersAfter['customers'])) {
                foreach ($customersAfter['customers'] as $customer) {
                    if ($customer['oid'] === $customerId) {
                        $customerStillExists = true;
                        break;
                    }
                }
            }

            if ($customerStillExists) {
                $this->error("❌ El customer aún existe después de la eliminación");
                return 1;
            }

            // 7. Resumen final
            $this->newLine();
            $this->info("🎉 ELIMINACIÓN COMPLETADA EXITOSAMENTE");
            $this->info("=====================================");
            $this->info("✅ Customer eliminado: {$customerId}");
            $this->info("✅ Suscripciones eliminadas: {$deletedSubscriptions}");
            $this->info("✅ Verificación completada");
            $this->newLine();
            $this->info("📝 NOTA: El usuario NO fue re-importado");
            $this->info("💡 Para re-importar, usa: php artisan baremetrics:complete-test-import {email}");

            // Log de la operación
            Log::info('Usuario eliminado completamente de Baremetrics', [
                'customer_id' => $customerId,
                'source_id' => $sourceId,
                'email' => $targetCustomer['email'] ?? 'N/A',
                'name' => $targetCustomer['name'] ?? 'N/A',
                'subscriptions_deleted' => $deletedSubscriptions,
                'subscriptions_failed' => $failedSubscriptions,
                'operation' => 'complete_deletion'
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Error durante la eliminación: " . $e->getMessage());
            Log::error('Error eliminando usuario de Baremetrics', [
                'customer_id' => $customerId,
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
