<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class TestPlanModification extends Command
{
    protected $signature = 'baremetrics:test-plan-modification';
    protected $description = 'Probar la capacidad de modificar planes de Creetelo via API';

    public function handle()
    {
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration();

        $this->info("🔧 Probando modificación de planes de Creetelo");
        $this->line("=============================================");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        // Obtener los planes actuales
        $this->info("\n📋 Obteniendo planes actuales...");
        $plans = $service->getPlans($sourceId);
        
        if (!$plans || !isset($plans['plans'])) {
            $this->error("❌ No se pudieron obtener los planes");
            return 1;
        }

        $this->info("✅ Se encontraron " . count($plans['plans']) . " planes:");
        
        foreach ($plans['plans'] as $plan) {
            $price = 'No especificado';
            if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                $amount = $plan['amounts'][0];
                $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
            }
            
            $this->line("📦 {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
        }

        // Probar crear un nuevo plan de prueba
        $this->info("\n🧪 Probando creación de plan de prueba...");
        
        $testPlanData = [
            'name' => 'test_plan_' . time(),
            'interval' => 'month',
            'interval_count' => 1,
            'amounts' => [
                [
                    'currency' => 'USD',
                    'amount' => 1000, // $10.00
                    'symbol' => '$',
                    'symbol_right' => false
                ]
            ],
            'active' => true
        ];

        $this->line("📝 Datos del plan de prueba:");
        $this->line("   • Nombre: {$testPlanData['name']}");
        $this->line("   • Intervalo: {$testPlanData['interval']}");
        $this->line("   • Precio: \${$testPlanData['amounts'][0]['amount']} {$testPlanData['amounts'][0]['currency']}");

        $newPlan = $service->createPlan($testPlanData, $sourceId);
        
        if ($newPlan) {
            $this->info("✅ Plan de prueba creado exitosamente!");
            $this->line("   • OID: {$newPlan['oid']}");
            $this->line("   • Nombre: {$newPlan['name']}");
            
            // Verificar que el plan se puede obtener
            $this->info("\n🔍 Verificando que el plan se puede obtener...");
            $retrievedPlan = $service->findPlanByName($testPlanData['name'], $sourceId);
            
            if ($retrievedPlan) {
                $this->info("✅ Plan recuperado exitosamente!");
                $this->line("   • OID: {$retrievedPlan['oid']}");
                $this->line("   • Nombre: {$retrievedPlan['name']}");
            } else {
                $this->warn("⚠️ No se pudo recuperar el plan creado");
            }
            
        } else {
            $this->error("❌ No se pudo crear el plan de prueba");
        }

        $this->info("\n✅ Prueba de modificación completada!");
        
        return 0;
    }
}
