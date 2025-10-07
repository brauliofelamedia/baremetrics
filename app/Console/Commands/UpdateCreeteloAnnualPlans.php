<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class UpdateCreeteloAnnualPlans extends Command
{
    protected $signature = 'baremetrics:update-creetelo-annual-plans';
    protected $description = 'Actualizar el precio de los planes anuales de Creetelo a $390';

    public function handle()
    {
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration();

        $this->info("🔄 Actualizando planes anuales de Creetelo a $390");
        $this->line("===============================================");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        // Planes anuales a actualizar
        $annualPlans = [
            [
                'name' => 'creetelo_anual',
                'oid' => '1759521447196',
                'current_price' => 0
            ],
            [
                'name' => 'créetelo_anual', 
                'oid' => '1759521461735',
                'current_price' => 0
            ]
        ];

        $this->info("\n📋 Planes anuales encontrados:");
        foreach ($annualPlans as $plan) {
            $this->line("   • {$plan['name']} - OID: {$plan['oid']} - Precio actual: $" . ($plan['current_price'] / 100));
        }

        // Confirmar la operación
        if (!$this->confirm("\n⚠️ ¿Estás seguro de que quieres actualizar estos planes a $390? Esta operación eliminará los planes actuales y creará nuevos.")) {
            $this->info("❌ Operación cancelada por el usuario");
            return 0;
        }

        $this->info("\n🔄 Iniciando actualización de planes...");

        foreach ($annualPlans as $plan) {
            $this->line("\n📦 Procesando: {$plan['name']}");
            
            // Crear nuevo plan con precio actualizado
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
                'oid' => $plan['oid'] // Mantener el mismo OID
            ];

            $this->line("   📝 Creando nuevo plan con precio $390...");
            
            $newPlan = $service->createPlan($newPlanData, $sourceId);
            
            if ($newPlan) {
                $this->info("   ✅ Plan actualizado exitosamente!");
                $this->line("      • OID: {$newPlan['oid']}");
                $this->line("      • Nombre: {$newPlan['name']}");
                $this->line("      • Precio: $390 USD (anual)");
            } else {
                $this->error("   ❌ Error al actualizar el plan: {$plan['name']}");
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

        $this->info("\n✅ ¡Actualización completada!");
        $this->warn("\n⚠️ IMPORTANTE: Los planes antiguos han sido reemplazados. Si tienes suscripciones activas, es posible que necesites actualizarlas para que apunten a los nuevos planes.");
        
        return 0;
    }
}
