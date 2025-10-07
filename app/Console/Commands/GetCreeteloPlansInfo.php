<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class GetCreeteloPlansInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baremetrics:get-creetelo-plans {--environment=sandbox}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtener información de los planes de Creetelo desde Baremetrics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environment = $this->option('environment');
        
        $this->info("🔍 Obteniendo información de planes de Creetelo");
        $this->line("=============================================");
        $this->line("Entorno: {$environment}");

        // Configurar entorno
        config(['services.baremetrics.environment' => $environment]);
        
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

        // Planes a buscar
        $planNames = [
            'creetelo_mensual',
            'créetelo_mensual', 
            'creetelo_anual',
            'créetelo_anual'
        ];

        $this->info("\n🔍 Buscando planes de Creetelo...");
        $this->line("=====================================");

        $foundPlans = [];
        $notFoundPlans = [];

        foreach ($planNames as $planName) {
            $this->line("\n📦 Buscando: {$planName}");
            
            // Buscar plan por nombre
            $plan = $baremetricsService->findPlanByName($planName, $sourceId);
            
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
                
                if (isset($plan['created'])) {
                    $this->line("      • Creado: " . date('Y-m-d H:i:s', $plan['created']));
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
                
                $this->line("   • {$plan['name']} - {$price} ({$plan['interval']})");
            }
        }

        if (!empty($notFoundPlans)) {
            $this->warn("\n⚠️ Planes no encontrados:");
            foreach ($notFoundPlans as $planName) {
                $this->line("   • {$planName}");
            }
        }

        // Mostrar todos los planes disponibles si no se encontraron los específicos
        if (empty($foundPlans)) {
            $this->info("\n🔍 Listando todos los planes disponibles...");
            $this->listAllPlans($baremetricsService, $sourceId);
        }

        // Guardar información en archivo
        $this->savePlansInfo($foundPlans, $environment);

        $this->info("\n✅ ¡Consulta completada!");
        
        return 0;
    }

    /**
     * Listar todos los planes disponibles
     */
    private function listAllPlans(BaremetricsService $service, string $sourceId)
    {
        try {
            $page = 1;
            $hasMore = true;
            $allPlans = [];
            
            while ($hasMore) {
                $response = $service->getPlans($sourceId, $page, 100);
                
                if (!$response) {
                    break;
                }

                $plans = [];
                if (is_array($response) && isset($response['plans']) && is_array($response['plans'])) {
                    $plans = $response['plans'];
                } elseif (is_array($response)) {
                    $plans = $response;
                }

                $allPlans = array_merge($allPlans, $plans);

                // Check if there are more pages
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }

                $page++;
            }

            if (!empty($allPlans)) {
                $this->line("\n📋 Todos los planes disponibles ({$sourceId}):");
                $this->line("=============================================");
                
                foreach ($allPlans as $plan) {
                    $price = 'No especificado';
                    if (isset($plan['amounts']) && is_array($plan['amounts']) && !empty($plan['amounts'])) {
                        $amount = $plan['amounts'][0];
                        $price = "{$amount['symbol']}{$amount['amount']} {$amount['currency']}";
                    }
                    
                    $this->line("   • {$plan['name']} - {$price} ({$plan['interval']}) - OID: {$plan['oid']}");
                }
            } else {
                $this->line("   • No se encontraron planes en este source");
            }

        } catch (\Exception $e) {
            $this->error("Error listando planes: " . $e->getMessage());
        }
    }

    /**
     * Guardar información de planes en archivo
     */
    private function savePlansInfo(array $plans, string $environment)
    {
        if (empty($plans)) {
            return;
        }

        $filename = "creetelo_plans_{$environment}_" . date('Y-m-d_H-i-s') . ".json";
        $filepath = storage_path("logs/{$filename}");

        $data = [
            'environment' => $environment,
            'timestamp' => now()->toISOString(),
            'plans' => $plans
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
        
        $this->info("\n💾 Información guardada en: {$filepath}");
    }
}
