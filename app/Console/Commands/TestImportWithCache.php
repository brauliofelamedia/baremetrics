<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TestImportWithCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:import-cache {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar importación con cache de planes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🧪 Probando importación con cache para: {$email}");
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

        // Usar cache para evitar planes duplicados
        $cacheKey = "baremetrics_plan_{$sourceId}_{$planData['name']}";
        
        $this->info("\n🔍 Verificando cache de planes...");
        $cachedPlan = Cache::get($cacheKey);
        
        if ($cachedPlan) {
            $this->line("   ✅ Plan encontrado en cache:");
            $this->line("      • OID: {$cachedPlan['oid']}");
            $this->line("      • Nombre: {$cachedPlan['name']}");
            $this->line("      • Intervalo: {$cachedPlan['interval']}");
            $plan = $cachedPlan;
        } else {
            $this->line("   ❌ Plan no encontrado en cache, creando nuevo...");
            
            // Crear nuevo plan
            $plan = $baremetricsService->createPlan($planData, $sourceId);
            
            if ($plan && isset($plan['plan']['oid'])) {
                // Guardar en cache por 24 horas
                Cache::put($cacheKey, $plan['plan'], 86400);
                
                $this->line("   ✅ Plan creado y guardado en cache:");
                $this->line("      • OID: {$plan['plan']['oid']}");
                $this->line("      • Nombre: {$plan['plan']['name']}");
                $this->line("      • Intervalo: {$plan['plan']['interval']}");
                
                $plan = $plan['plan']; // Usar el plan directamente
            } else {
                $this->error("   ❌ Error creando plan");
                return 1;
            }
        }

        // Probar segunda llamada para verificar cache
        $this->info("\n🔄 Probando segunda llamada (debería usar cache)...");
        $cachedPlan2 = Cache::get($cacheKey);
        
        if ($cachedPlan2) {
            if ($cachedPlan2['oid'] === $plan['oid']) {
                $this->line("   ✅ Cache funcionando correctamente (mismo OID)");
            } else {
                $this->warn("   ⚠️ Cache con OID diferente");
            }
        } else {
            $this->error("   ❌ Cache no encontrado en segunda llamada");
        }

        $this->info("\n✅ ¡Prueba de cache completada exitosamente!");
        
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
