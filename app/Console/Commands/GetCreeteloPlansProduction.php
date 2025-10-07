<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class GetCreeteloPlansProduction extends Command
{
    protected $signature = 'baremetrics:get-creetelo-plans-production';
    protected $description = 'Obtener planes de Creetelo desde API de producción usando el source correcto';

    public function handle()
    {
        // Configurar entorno de producción ANTES de crear el servicio
        config(['services.baremetrics.environment' => 'production']);
        
        // Verificar configuración
        $this->info("🔧 Verificando configuración...");
        $this->line("   • Entorno configurado: " . config('services.baremetrics.environment'));
        $this->line("   • API Key de producción: " . (config('services.baremetrics.live_key') ? 'Configurada' : 'No configurada'));
        $this->line("   • URL de producción: " . config('services.baremetrics.production_url'));
        
        $service = new BaremetricsService();
        $service->reinitializeConfiguration(); // Reinicializar con la nueva configuración
        
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8'; // Source correcto de baremetrics manual

        $this->info("\n🔍 Obteniendo planes de Creetelo desde API de Producción");
        $this->line("=======================================================");
        $this->line("Source ID: {$sourceId}");
        $this->line("Entorno: " . ($service->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $service->getBaseUrl());

        // Obtener todos los planes del source
        $this->info("\n📋 Obteniendo todos los planes del source...");
        $plans = $service->getPlans($sourceId);

        if ($plans && isset($plans['plans'])) {
            $this->info("✅ Se encontraron " . count($plans['plans']) . " planes:");
            
            foreach ($plans['plans'] as $plan) {
                $price = 'No especificado';
                if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                    $amount = $plan['amounts'][0];
                    $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                }
                
                $this->line("\n📦 Plan: {$plan['name']}");
                $this->line("   • OID: {$plan['oid']}");
                $this->line("   • Intervalo: {$plan['interval']}");
                $this->line("   • Cantidad: {$plan['interval_count']}");
                $this->line("   • Precio: {$price}");
                $this->line("   • Activo: " . ($plan['active'] ? 'Sí' : 'No'));
                $this->line("   • Creado: " . (isset($plan['created']) ? date('Y-m-d H:i:s', $plan['created']) : 'No especificado'));
            }
        } else {
            $this->error("❌ No se pudieron obtener los planes");
        }

        // Buscar planes específicos de Creetelo
        $this->info("\n🔍 Buscando planes específicos de Creetelo...");
        $planNames = ['creetelo_mensual', 'créetelo_mensual', 'creetelo_anual', 'créetelo_anual'];

        $foundPlans = [];
        $notFoundPlans = [];

        foreach ($planNames as $planName) {
            $this->line("\n📦 Buscando: {$planName}");
            $plan = $service->findPlanByName($planName, $sourceId);
            
            if ($plan) {
                $foundPlans[] = $plan;
                $this->line("   ✅ Plan encontrado:");
                $this->line("      • OID: {$plan['oid']}");
                $this->line("      • Nombre: {$plan['name']}");
                $this->line("      • Intervalo: {$plan['interval']}");
                $this->line("      • Cantidad: {$plan['interval_count']}");
                $this->line("      • Activo: " . ($plan['active'] ? 'Sí' : 'No'));
                
                if (isset($plan['amounts']) && is_array($plan['amounts'])) {
                    foreach ($plan['amounts'] as $amount) {
                        $this->line("      • Precio: {$amount['symbol']}{$amount['amount']} {$amount['currency']}");
                    }
                } else {
                    $this->line("      • Precio: No especificado");
                }
            } else {
                $notFoundPlans[] = $planName;
                $this->line("   ❌ Plan no encontrado");
            }
        }

        // Resumen
        $this->info("\n📊 Resumen:");
        $this->line("===========");
        $this->line("   • Planes encontrados: " . count($foundPlans));
        $this->line("   • Planes no encontrados: " . count($notFoundPlans));

        if (!empty($foundPlans)) {
            $this->info("\n✅ Planes encontrados:");
            foreach ($foundPlans as $plan) {
                $price = 'No especificado';
                if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                    $amount = $plan['amounts'][0];
                    $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                }
                
                $this->line("   • {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
            }
        }

        if (!empty($notFoundPlans)) {
            $this->warn("\n⚠️ Planes no encontrados:");
            foreach ($notFoundPlans as $planName) {
                $this->line("   • {$planName}");
            }
        }

        $this->info("\n✅ ¡Consulta completada!");
        
        return 0;
    }
}
