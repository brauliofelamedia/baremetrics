<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class MarkUserAsMigrated extends Command
{
    protected $signature = 'baremetrics:mark-migrated {email}';
    protected $description = 'Marca un usuario como migrado desde GHL en Baremetrics';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🏷️ Marcando usuario como migrado: {$email}");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Buscar cliente en Baremetrics
            $this->info("🔍 Buscando cliente en Baremetrics...");
            $customers = $baremetricsService->getCustomers($sourceId);
            
            $userCustomer = null;
            if ($customers && isset($customers['customers'])) {
                foreach ($customers['customers'] as $customer) {
                    if (strtolower($customer['email']) === strtolower($email)) {
                        $userCustomer = $customer;
                        break;
                    }
                }
            }

            if (!$userCustomer) {
                $this->error("❌ No se encontró el cliente en Baremetrics");
                return;
            }

            $customerOid = $userCustomer['oid'];
            $this->info("✅ Cliente encontrado: {$customerOid}");

            // 2. Marcar como migrado desde GHL
            $this->info("🏷️ Marcando como migrado desde GHL...");
            
            $migrationData = [
                'GHL: Migrate GHL' => true,
            ];

            $updateResult = $baremetricsService->updateCustomerAttributes($customerOid, $migrationData);
            
            if ($updateResult) {
                $this->info("✅ Usuario marcado como migrado exitosamente");
                $this->info("🏷️ GHL: Migrate GHL = true");
            } else {
                $this->error("❌ Error marcando usuario como migrado");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante el marcado: " . $e->getMessage());
            Log::error('Error marcando usuario como migrado', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
