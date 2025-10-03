<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Http;

class CheckBaremetricsStatus extends Command
{
    protected $signature = 'baremetrics:check-status {--customer-oid= : OID específico del cliente}';
    protected $description = 'Verifica el estado actual de clientes y suscripciones en Baremetrics';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $this->info('🔍 VERIFICANDO ESTADO EN BAREMETRICS');
        $this->info('===================================');

        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No se pudo obtener el Source ID');
            return 1;
        }

        $this->info("📋 Source ID: {$sourceId}");
        $this->newLine();

        // Verificar clientes
        $this->checkCustomers($sourceId);

        // Verificar suscripciones
        $this->checkSubscriptions($sourceId);

        return 0;
    }

    private function checkCustomers(string $sourceId): void
    {
        $this->info('👥 CLIENTES EN BAREMETRICS');
        $this->info('==========================');

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

                $this->line("Total de clientes: " . count($customers));
                $this->newLine();

                // Mostrar últimos 10 clientes
                $recentCustomers = array_slice($customers, -10);
                
                $this->table(
                    ['OID', 'Nombre', 'Email', 'Activo', 'MRR', 'Planes'],
                    array_map(function ($customer) {
                        return [
                            substr($customer['oid'], 0, 15) . '...',
                            $customer['name'] ?? 'N/A',
                            $customer['email'] ?? 'N/A',
                            $customer['is_active'] ? '✅ Sí' : '❌ No',
                            '$' . number_format($customer['current_mrr'] / 100, 2),
                            count($customer['current_plans'] ?? [])
                        ];
                    }, $recentCustomers)
                );

            } else {
                $this->error("Error al obtener clientes: " . $response->status());
                $this->line($response->body());
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function checkSubscriptions(string $sourceId): void
    {
        $this->info('📋 SUSCRIPCIONES EN BAREMETRICS');
        $this->info('===============================');

        try {
            $environment = config('services.baremetrics.environment', 'sandbox');
            $baseUrl = config("services.baremetrics.{$environment}_url");
            $apiKey = config("services.baremetrics.{$environment}_key");
            
            $url = "{$baseUrl}/{$sourceId}/subscriptions";
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $subscriptions = $data['subscriptions'] ?? [];

                $this->line("Total de suscripciones: " . count($subscriptions));
                $this->newLine();

                // Mostrar últimas 10 suscripciones
                $recentSubscriptions = array_slice($subscriptions, -10);
                
                $this->table(
                    ['OID', 'Cliente', 'Plan', 'Estado', 'Inicio', 'Cancelado'],
                    array_map(function ($subscription) {
                        $customerOid = $subscription['customer_oid'] ?? $subscription['customer']['oid'] ?? 'N/A';
                        $planOid = $subscription['plan_oid'] ?? $subscription['plan']['oid'] ?? 'N/A';
                        
                        return [
                            substr($subscription['oid'], 0, 15) . '...',
                            is_string($customerOid) ? substr($customerOid, 0, 15) . '...' : 'N/A',
                            is_string($planOid) ? substr($planOid, 0, 15) . '...' : 'N/A',
                            $subscription['status'] ?? 'N/A',
                            date('Y-m-d', $subscription['started_at'] ?? 0),
                            $subscription['canceled_at'] ? date('Y-m-d', $subscription['canceled_at']) : 'No'
                        ];
                    }, $recentSubscriptions)
                );

            } else {
                $this->error("Error al obtener suscripciones: " . $response->status());
                $this->line($response->body());
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
        }

        $this->newLine();
    }
}
