<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Http;

class DeleteAndRecreateAnnualPlans extends Command
{
    protected $signature = 'baremetrics:delete-recreate-annual-plans';
    protected $description = 'Eliminar planes anuales existentes y crear nuevos con precio $390';

    public function handle()
    {
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration();

        $this->info("🗑️ Eliminando y recreando planes anuales de Creetelo");
        $this->line("==================================================");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        // Planes anuales a procesar
        $annualPlans = [
            [
                'name' => 'creetelo_anual',
                'oid' => '1759521447196'
            ],
            [
                'name' => 'créetelo_anual', 
                'oid' => '1759521461735'
            ]
        ];

        $this->info("\n📋 Planes anuales a procesar:");
        foreach ($annualPlans as $plan) {
            $this->line("   • {$plan['name']} - OID: {$plan['oid']}");
        }

        // Confirmar la operación
        if (!$this->confirm("\n⚠️ ¿Estás seguro de que quieres eliminar estos planes y crear nuevos con precio $390?")) {
            $this->info("❌ Operación cancelada por el usuario");
            return 0;
        }

        $this->info("\n🔄 Iniciando proceso de eliminación y recreación...");

        foreach ($annualPlans as $plan) {
            $this->line("\n📦 Procesando: {$plan['name']}");
            
            // Paso 1: Eliminar el plan existente
            $this->line("   🗑️ Eliminando plan existente...");
            $deleteResult = $this->deletePlan($plan['oid'], $sourceId, $service);
            
            if ($deleteResult) {
                $this->info("   ✅ Plan eliminado exitosamente");
                
                // Paso 2: Crear nuevo plan con precio actualizado
                $this->line("   📝 Creando nuevo plan con precio $390...");
                
                $newPlanData = [
                    'name' => $plan['name'],
                    'interval' => 'year',
                    'interval_count' => 1,
                    'amounts' => [
                        [
                            'currency' => 'USD',
                            'amount' => 39000, // $390 en centavos
                            'symbol' => '$',
                            'symbol_right' => false
                        ]
                    ],
                    'active' => true,
                    'oid' => $plan['oid'] // Usar el mismo OID
                ];

                $newPlan = $service->createPlan($newPlanData, $sourceId);
                
                if ($newPlan) {
                    $this->info("   ✅ Nuevo plan creado exitosamente!");
                    $this->line("      • OID: {$newPlan['oid']}");
                    $this->line("      • Nombre: {$newPlan['name']}");
                    $this->line("      • Precio: $390 USD (anual)");
                } else {
                    $this->error("   ❌ Error al crear el nuevo plan: {$plan['name']}");
                }
            } else {
                $this->error("   ❌ Error al eliminar el plan: {$plan['name']}");
            }
        }

        // Verificar los planes actualizados
        $this->info("\n🔍 Verificando planes actualizados...");
        $plans = $service->getPlans($sourceId);
        
        if ($plans && isset($plans['plans'])) {
            $this->info("✅ Planes actuales en el source:");
            foreach ($plans['plans'] as $plan) {
                $price = 'No especificado';
                if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                    $amount = $plan['amounts'][0];
                    $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                }
                
                $this->line("   📦 {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
            }
        }

        $this->info("\n✅ ¡Proceso completado!");
        
        return 0;
    }

    /**
     * Eliminar un plan de Baremetrics
     */
    private function deletePlan(string $planOid, string $sourceId, BaremetricsService $service): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $service->getApiKey(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->delete($service->getBaseUrl() . "/{$sourceId}/plans/{$planOid}");

            if ($response->successful()) {
                return true;
            }

            // Si el plan no existe (404), considerarlo como éxito
            if ($response->status() === 404) {
                return true;
            }

            $this->error("   ❌ Error HTTP {$response->status()}: {$response->body()}");
            return false;

        } catch (\Exception $e) {
            $this->error("   ❌ Excepción: {$e->getMessage()}");
            return false;
        }
    }
}
