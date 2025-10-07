<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Http;

class FixCreeteloAnnualPlan extends Command
{
    protected $signature = 'baremetrics:fix-creetelo-annual-plan';
    protected $description = 'Eliminar plan creetelo_anual incorrecto (mensual) y crear el correcto (anual)';

    public function handle()
    {
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration();

        $this->info("🔧 Corrigiendo plan creetelo_anual");
        $this->line("================================");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        // Primero, obtener todos los planes para encontrar el incorrecto
        $this->info("\n🔍 Buscando plan creetelo_anual incorrecto...");
        $plans = $service->getPlans($sourceId);
        
        if (!$plans || !isset($plans['plans'])) {
            $this->error("❌ No se pudieron obtener los planes");
            return 1;
        }

        $incorrectPlan = null;
        foreach ($plans['plans'] as $plan) {
            if ($plan['name'] === 'creetelo_anual' && $plan['interval'] === 'month') {
                $incorrectPlan = $plan;
                break;
            }
        }

        if (!$incorrectPlan) {
            $this->warn("⚠️ No se encontró el plan creetelo_anual con intervalo mensual");
            $this->info("📋 Planes actuales:");
            foreach ($plans['plans'] as $plan) {
                $price = 'No especificado';
                if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                    $amount = $plan['amounts'][0];
                    $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                }
                $this->line("   📦 {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
            }
            return 0;
        }

        $this->info("✅ Plan incorrecto encontrado:");
        $this->line("   • Nombre: {$incorrectPlan['name']}");
        $this->line("   • Intervalo: {$incorrectPlan['interval']} (debería ser 'year')");
        $this->line("   • OID: {$incorrectPlan['oid']}");
        
        $price = 'No especificado';
        if (isset($incorrectPlan['amounts']) && is_array($incorrectPlan['amounts']) && !empty($incorrectPlan['amounts'])) {
            $amount = $incorrectPlan['amounts'][0];
            $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
        }
        $this->line("   • Precio: {$price}");

        // Confirmar la operación
        if (!$this->confirm("\n⚠️ ¿Eliminar este plan incorrecto y crear uno correcto con intervalo anual?")) {
            $this->info("❌ Operación cancelada por el usuario");
            return 0;
        }

        // Paso 1: Eliminar el plan incorrecto
        $this->line("\n🗑️ Eliminando plan incorrecto...");
        $deleteResult = $this->deletePlan($incorrectPlan['oid'], $sourceId, $service);
        
        if (!$deleteResult) {
            $this->error("❌ Error al eliminar el plan incorrecto");
            return 1;
        }
        
        $this->info("✅ Plan incorrecto eliminado exitosamente");

        // Paso 2: Crear el plan correcto
        $this->line("\n📝 Creando plan correcto con intervalo anual...");
        
        $correctPlanData = [
            'name' => 'creetelo_anual',
            'interval' => 'year', // Correcto: anual
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
            'oid' => $incorrectPlan['oid'] // Usar el mismo OID
        ];

        $newPlan = $service->createPlan($correctPlanData, $sourceId);
        
        if ($newPlan) {
            $this->info("✅ Plan correcto creado exitosamente!");
            $this->line("   • OID: {$newPlan['oid']}");
            $this->line("   • Nombre: {$newPlan['name']}");
            $this->line("   • Intervalo: {$newPlan['interval']}");
            $this->line("   • Precio: $390 USD (anual)");
        } else {
            $this->error("❌ Error al crear el plan correcto");
            return 1;
        }

        // Verificar los planes finales
        $this->info("\n🔍 Verificando planes finales...");
        $finalPlans = $service->getPlans($sourceId);
        
        if ($finalPlans && isset($finalPlans['plans'])) {
            $this->info("✅ Planes actuales en el source:");
            foreach ($finalPlans['plans'] as $plan) {
                $price = 'No especificado';
                if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                    $amount = $plan['amounts'][0];
                    $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                }
                
                $this->line("   📦 {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
            }
        }

        $this->info("\n✅ ¡Corrección completada!");
        
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
