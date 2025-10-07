<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DiagnoseImportError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diagnose:import-error {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnosticar error de importación para un usuario específico';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔍 Diagnosticando error de importación para: {$email}");
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
        $this->line("   • Estado: {$user->import_status}");

        // Verificar configuración de Baremetrics
        $baremetricsService = new BaremetricsService();
        
        $this->info("\n📋 Configuración de Baremetrics:");
        $this->line("   • Entorno: " . ($baremetricsService->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("   • Base URL: " . $baremetricsService->getBaseUrl());
        $this->line("   • API Key: " . substr($baremetricsService->getApiKey(), 0, 10) . '...');

        // Probar conexión básica
        $this->info("\n🌐 Probando conexión básica...");
        try {
            $account = $baremetricsService->getAccount();
            if ($account) {
                $this->line("   ✅ Conexión exitosa");
                $this->line("   • Account ID: " . ($account['account']['id'] ?? 'N/A'));
            } else {
                $this->error("   ❌ No se pudo obtener información de la cuenta");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error de conexión: " . $e->getMessage());
        }

        // Probar obtener sources
        $this->info("\n🔗 Probando obtención de sources...");
        try {
            $sourceId = $baremetricsService->getSourceId();
            if ($sourceId) {
                $this->line("   ✅ Source ID obtenido: {$sourceId}");
            } else {
                $this->error("   ❌ No se pudo obtener Source ID");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error obteniendo Source ID: " . $e->getMessage());
            return 1;
        }

        // Determinar plan
        $planData = $this->determinePlanFromTags($user->tags);
        
        $this->info("\n📦 Plan que se intentaría crear:");
        $this->line("   • Nombre: {$planData['name']}");
        $this->line("   • Intervalo: {$planData['interval']}");
        $this->line("   • Cantidad: {$planData['interval_count']}");
        $this->line("   • Precio: \${$planData['amount']} {$planData['currency']}");

        // Probar creación de cliente paso a paso
        $this->info("\n🧪 Probando creación paso a paso...");

        // 1. Crear cliente
        $customerData = [
            'name' => $user->name,
            'email' => $user->email,
            'company' => $user->company,
            'notes' => "Importado desde GHL - Tags: {$user->tags}",
            'oid' => 'cust_' . uniqid(),
        ];

        $this->line("   1️⃣ Creando cliente...");
        try {
            $customer = $baremetricsService->createCustomer($customerData, $sourceId);
            if ($customer && isset($customer['customer']['oid'])) {
                $this->line("      ✅ Cliente creado: {$customer['customer']['oid']}");
                $customerOid = $customer['customer']['oid'];
            } else {
                $this->error("      ❌ Error creando cliente");
                $this->line("      Respuesta: " . json_encode($customer));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("      ❌ Excepción creando cliente: " . $e->getMessage());
            return 1;
        }

        // 2. Crear plan
        $this->line("   2️⃣ Creando plan...");
        try {
            $plan = $baremetricsService->createPlan($planData, $sourceId);
            if ($plan && isset($plan['plan']['oid'])) {
                $this->line("      ✅ Plan creado: {$plan['plan']['oid']}");
                $planOid = $plan['plan']['oid'];
            } else {
                $this->error("      ❌ Error creando plan");
                $this->line("      Respuesta: " . json_encode($plan));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("      ❌ Excepción creando plan: " . $e->getMessage());
            return 1;
        }

        // 3. Crear suscripción
        $subscriptionData = [
            'oid' => 'sub_' . uniqid(),
            'customer_oid' => $customerOid,
            'plan_oid' => $planOid,
            'started_at' => now()->timestamp, // Usar timestamp Unix
            'status' => 'active',
            'canceled_at' => null,
            'canceled_reason' => null,
        ];

        $this->line("   3️⃣ Creando suscripción...");
        try {
            $subscription = $baremetricsService->createSubscription($subscriptionData, $sourceId);
            if ($subscription) {
                $this->line("      ✅ Suscripción creada exitosamente");
                $this->line("      Respuesta: " . json_encode($subscription));
            } else {
                $this->error("      ❌ Error creando suscripción");
                $this->line("      Datos enviados: " . json_encode($subscriptionData));
                $this->line("      Source ID: {$sourceId}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("      ❌ Excepción creando suscripción: " . $e->getMessage());
            $this->line("      Datos enviados: " . json_encode($subscriptionData));
            $this->line("      Source ID: {$sourceId}");
            return 1;
        }

        $this->info("\n✅ ¡Diagnóstico completado exitosamente!");
        $this->line("Todos los pasos funcionaron correctamente.");
        
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
