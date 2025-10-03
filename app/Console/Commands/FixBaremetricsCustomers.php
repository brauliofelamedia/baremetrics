<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Http;

class FixBaremetricsCustomers extends Command
{
    protected $signature = 'baremetrics:fix-customers {--limit=5 : Número de clientes a procesar}';
    protected $description = 'Intenta reactivar clientes inactivos en Baremetrics creando suscripciones con fechas actuales';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $this->info('🔧 REACTIVANDO CLIENTES INACTIVOS EN BAREMETRICS');
        $this->info('================================================');

        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No se pudo obtener el Source ID');
            return 1;
        }

        $limit = (int) $this->option('limit');
        $this->info("📋 Procesando máximo {$limit} clientes inactivos");
        $this->newLine();

        // Obtener clientes inactivos
        $inactiveCustomers = $this->getInactiveCustomers($sourceId, $limit);
        
        if (empty($inactiveCustomers)) {
            $this->info('✅ No se encontraron clientes inactivos');
            return 0;
        }

        $this->info("🔍 Se encontraron " . count($inactiveCustomers) . " clientes inactivos");
        $this->newLine();

        // Obtener plan activo
        $activePlan = $this->getActivePlan($sourceId);
        if (!$activePlan) {
            $this->error('❌ No se encontró un plan activo');
            return 1;
        }

        $this->info("📋 Usando plan: {$activePlan['name']} ({$activePlan['oid']})");
        $this->newLine();

        // Procesar clientes
        $processed = 0;
        $success = 0;
        $errors = 0;

        foreach ($inactiveCustomers as $customer) {
            $this->line("👤 Procesando: {$customer['name']} ({$customer['email']})");
            
            try {
                $result = $this->createSubscriptionForCustomer($customer, $activePlan, $sourceId);
                
                if ($result) {
                    $this->line("   ✅ Suscripción creada exitosamente");
                    $success++;
                } else {
                    $this->line("   ❌ Error al crear suscripción");
                    $errors++;
                }
                
                $processed++;
                
                // Pausa entre solicitudes
                sleep(1);
                
            } catch (\Exception $e) {
                $this->line("   ❌ Error: " . $e->getMessage());
                $errors++;
                $processed++;
            }
        }

        $this->newLine();
        $this->info('📊 RESUMEN');
        $this->info('==========');
        $this->line("✅ Procesados: {$processed}");
        $this->line("✅ Exitosos: {$success}");
        $this->line("❌ Errores: {$errors}");

        if ($success > 0) {
            $this->newLine();
            $this->info("🎉 ¡Se reactivaron {$success} clientes! Verifica en Baremetrics.");
        }

        return 0;
    }

    private function getInactiveCustomers(string $sourceId, int $limit): array
    {
        try {
            $environment = config('services.baremetrics.environment', 'sandbox');
            $baseUrl = config("services.baremetrics.{$environment}_url");
            $apiKey = config("services.baremetrics.{$environment}_key");
            
            $url = "{$baseUrl}/{$sourceId}/customers";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $customers = $data['customers'] ?? [];
                
                // Filtrar solo clientes inactivos
                $inactiveCustomers = array_filter($customers, function ($customer) {
                    return !$customer['is_active'] && empty($customer['current_plans']);
                });
                
                return array_slice($inactiveCustomers, 0, $limit);
            }

            return [];
        } catch (\Exception $e) {
            $this->error("Error obteniendo clientes: " . $e->getMessage());
            return [];
        }
    }

    private function getActivePlan(string $sourceId): ?array
    {
        try {
            $environment = config('services.baremetrics.environment', 'sandbox');
            $baseUrl = config("services.baremetrics.{$environment}_url");
            $apiKey = config("services.baremetrics.{$environment}_key");
            
            $url = "{$baseUrl}/{$sourceId}/plans";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $plans = $data['plans'] ?? [];
                
                // Buscar el plan más reciente activo
                foreach (array_reverse($plans) as $plan) {
                    if ($plan['active']) {
                        return $plan;
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->error("Error obteniendo planes: " . $e->getMessage());
            return null;
        }
    }

    private function createSubscriptionForCustomer(array $customer, array $plan, string $sourceId): bool
    {
        try {
            $environment = config('services.baremetrics.environment', 'sandbox');
            $baseUrl = config("services.baremetrics.{$environment}_url");
            $apiKey = config("services.baremetrics.{$environment}_key");
            
            $url = "{$baseUrl}/{$sourceId}/subscriptions";
            
            $subscriptionData = [
                'customer_oid' => $customer['oid'],
                'plan_oid' => $plan['oid'],
                'started_at' => now()->timestamp, // Fecha actual
                'status' => 'active',
                'notes' => 'Suscripción creada para reactivar cliente inactivo',
                'oid' => 'sub_' . uniqid() // Generar OID único
            ];
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($url, $subscriptionData);

            if ($response->successful()) {
                $responseData = $response->json();
                $subscriptionOid = $responseData['subscription']['oid'] ?? $responseData['oid'] ?? 'N/A';
                $this->line("   📋 Suscripción creada: {$subscriptionOid}");
                return true;
            } else {
                $this->line("   ❌ Error API: " . $response->status() . " - " . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            $this->line("   ❌ Error: " . $e->getMessage());
            return false;
        }
    }
}
