<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class TestAllBaremetricsSources extends Command
{
    protected $signature = 'baremetrics:test-all-sources';
    protected $description = 'Probar todos los sources de Baremetrics para encontrar los planes de Creetelo';

    public function handle()
    {
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration();

        $this->info("🔍 Probando todos los sources de Baremetrics");
        $this->line("==========================================");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        // Obtener todos los sources
        $sources = $service->getSources();
        
        if (!$sources || !isset($sources['sources'])) {
            $this->error("❌ No se pudieron obtener los sources");
            return 1;
        }

        $this->info("\n📋 Sources encontrados: " . count($sources['sources']));
        
        $planNames = ['creetelo_mensual', 'créetelo_mensual', 'creetelo_anual', 'créetelo_anual'];
        
        foreach ($sources['sources'] as $source) {
            $sourceId = $source['id'];
            $provider = $source['provider'];
            
            $this->line("\n🔍 Probando Source: {$sourceId}");
            $this->line("   • Provider: {$provider}");
            
            // Obtener planes de este source
            $plans = $service->getPlans($sourceId);
            
            if ($plans && isset($plans['plans'])) {
                $this->line("   • Planes encontrados: " . count($plans['plans']));
                
                // Mostrar todos los planes
                foreach ($plans['plans'] as $plan) {
                    $price = 'No especificado';
                    if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                        $amount = $plan['amounts'][0];
                        $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                    }
                    
                    $this->line("     📦 {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
                }
                
                // Buscar planes específicos de Creetelo
                $this->line("   🔍 Buscando planes específicos de Creetelo...");
                foreach ($planNames as $planName) {
                    $plan = $service->findPlanByName($planName, $sourceId);
                    if ($plan) {
                        $this->line("     ✅ Encontrado: {$planName} - OID: {$plan['oid']}");
                    }
                }
                
            } else {
                $this->line("   • No se encontraron planes en este source");
            }
        }

        $this->info("\n✅ Prueba completada!");
        
        return 0;
    }
}
