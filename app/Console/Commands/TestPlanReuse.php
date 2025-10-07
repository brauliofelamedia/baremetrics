<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class TestPlanReuse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:plan-reuse {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar reutilización de planes existentes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔍 Probando reutilización de planes para: {$email}");
        $this->line("==================================================");

        // Buscar el usuario
        $user = MissingUser::where('email', $email)->first();
        if (!$user) {
            $this->error("❌ No se encontró el usuario con email: {$email}");
            return 1;
        }

        $this->info("\n👤 Usuario encontrado:");
        $this->line("   • ID: {$user->id}");
        $this->line("   • Email: {$user->email}");
        $this->line("   • Nombre: {$user->name}");
        $this->line("   • Tags: {$user->tags}");

        // Forzar entorno sandbox
        config(['services.baremetrics.environment' => 'sandbox']);
        
        $baremetricsService = new BaremetricsService();
        
        $this->info("\n📋 Configuración:");
        $this->line("   • Entorno: " . ($baremetricsService->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("   • Base URL: " . $baremetricsService->getBaseUrl());

        // Obtener source ID
        $sourceId = $baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error("❌ No se pudo obtener Source ID");
            return 1;
        }
        $this->line("   • Source ID: {$sourceId}");

        // Determinar plan
        $planData = $this->determinePlanFromTags($user->tags);
        
        $this->info("\n📦 Plan a buscar/crear:");
        $this->line("   • Nombre: {$planData['name']}");
        $this->line("   • Intervalo: {$planData['interval']}");
        $this->line("   • Cantidad: {$planData['interval_count']}");
        $this->line("   • Precio: \${$planData['amount']} {$planData['currency']}");

        // Probar búsqueda de plan existente
        $this->info("\n🔍 Buscando plan existente...");
        $existingPlan = $baremetricsService->findPlanByName($planData['name'], $sourceId);
        
        if ($existingPlan) {
            $this->line("   ✅ Plan encontrado:");
            $this->line("      • OID: {$existingPlan['oid']}");
            $this->line("      • Nombre: {$existingPlan['name']}");
            $this->line("      • Intervalo: {$existingPlan['interval']}");
            $this->line("      • Activo: " . ($existingPlan['active'] ? 'Sí' : 'No'));
            
            if (isset($existingPlan['amounts']) && is_array($existingPlan['amounts'])) {
                foreach ($existingPlan['amounts'] as $amount) {
                    $this->line("      • Precio: \${$amount['amount']} {$amount['currency']}");
                }
            }
        } else {
            $this->line("   ❌ Plan no encontrado, se creará uno nuevo");
        }

        // Probar findOrCreatePlan
        $this->info("\n🔄 Probando findOrCreatePlan...");
        $plan = $baremetricsService->findOrCreatePlan($planData, $sourceId);
        
        if ($plan && isset($plan['oid'])) {
            $this->line("   ✅ Plan obtenido/creado:");
            $this->line("      • OID: {$plan['oid']}");
            $this->line("      • Nombre: {$plan['name']}");
            $this->line("      • Intervalo: {$plan['interval']}");
            $this->line("      • Activo: " . ($plan['active'] ? 'Sí' : 'No'));
            
            if (isset($plan['amounts']) && is_array($plan['amounts'])) {
                foreach ($plan['amounts'] as $amount) {
                    $this->line("      • Precio: \${$amount['amount']} {$amount['currency']}");
                }
            }
        } else {
            $this->error("   ❌ Error obteniendo/creando plan");
            return 1;
        }

        // Probar segunda llamada para verificar reutilización
        $this->info("\n🔄 Probando segunda llamada (debería reutilizar)...");
        $plan2 = $baremetricsService->findOrCreatePlan($planData, $sourceId);
        
        if ($plan2 && isset($plan2['oid'])) {
            if ($plan2['oid'] === $plan['oid']) {
                $this->line("   ✅ Plan reutilizado correctamente (mismo OID)");
            } else {
                $this->warn("   ⚠️ Se creó un plan diferente (OID diferente)");
                $this->line("      • Primer OID: {$plan['oid']}");
                $this->line("      • Segundo OID: {$plan2['oid']}");
            }
        } else {
            $this->error("   ❌ Error en segunda llamada");
            return 1;
        }

        $this->info("\n✅ ¡Prueba completada exitosamente!");
        
        return 0;
    }

    /**
     * Determinar el plan basado en los tags del usuario
     */
    private function determinePlanFromTags(?string $tags): array
    {
        if (empty($tags)) {
            return [
                'name' => 'creetelo_mensual',
                'interval' => 'month',
                'interval_count' => 1,
                'amount' => 0,
                'currency' => 'usd',
                'oid' => 'plan_' . uniqid(),
            ];
        }

        $tagsArray = array_map('trim', explode(',', $tags));
        
        foreach ($tagsArray as $tag) {
            $tag = strtolower($tag);
            
            if (strpos($tag, 'creetelo_anual') !== false || strpos($tag, 'créetelo_anual') !== false) {
                return [
                    'name' => 'creetelo_anual',
                    'interval' => 'year',
                    'interval_count' => 1,
                    'amount' => 0,
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
            
            if (strpos($tag, 'creetelo_mensual') !== false || strpos($tag, 'créetelo_mensual') !== false) {
                return [
                    'name' => 'creetelo_mensual',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => 0,
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
        }

        $firstTag = trim($tagsArray[0]);
        $interval = 'month';
        
        if (strpos($firstTag, 'anual') !== false || strpos($firstTag, 'year') !== false) {
            $interval = 'year';
        }

        return [
            'name' => $firstTag,
            'interval' => $interval,
            'interval_count' => 1,
            'amount' => 0,
            'currency' => 'usd',
            'oid' => 'plan_' . uniqid(),
        ];
    }
}
