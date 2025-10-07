<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class TestImportWithSandbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:import-sandbox {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar importación con entorno sandbox forzado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🧪 Probando importación con sandbox para: {$email}");
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
        $this->line("   • Estado actual: {$user->import_status}");

        // Forzar entorno sandbox
        config(['services.baremetrics.environment' => 'sandbox']);
        
        $baremetricsService = new BaremetricsService();
        
        $this->info("\n📋 Configuración forzada:");
        $this->line("   • Entorno: " . ($baremetricsService->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("   • Base URL: " . $baremetricsService->getBaseUrl());

        $this->info("\n🔄 Iniciando importación automáticamente...");
        
        try {
            // Marcar como importando
            $user->markAsImporting();
            $this->line("   ✅ Usuario marcado como 'importando'");

            // Determinar el plan basado en los tags del usuario
            $planData = $this->determinePlanFromTags($user->tags);
            
            // Crear datos del cliente
            $customerData = [
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company,
                'notes' => "Importado desde GHL - Tags: {$user->tags}",
                'oid' => 'cust_' . uniqid(),
            ];

            // Crear datos de suscripción
            $subscriptionData = [
                'oid' => 'sub_' . uniqid(),
                'started_at' => now()->timestamp, // Usar timestamp Unix
                'status' => 'active',
                'canceled_at' => null,
                'canceled_reason' => null,
            ];

            $this->line("   📝 Datos preparados:");
            $this->line("      • Plan: {$planData['name']} ({$planData['interval']})");
            $this->line("      • Cliente: {$customerData['name']} - {$customerData['email']}");

            // Crear configuración completa del cliente en Baremetrics
            $this->line("   🌐 Enviando datos a Baremetrics...");
            
            $result = $baremetricsService->createCompleteCustomerSetup(
                $customerData,
                $planData,
                $subscriptionData
            );

            if ($result && isset($result['customer']['customer']['oid'])) {
                $customerOid = $result['customer']['customer']['oid'];
                $user->markAsImported($customerOid);

                $this->info("\n✅ ¡Importación exitosa!");
                $this->line("   • Customer OID: {$customerOid}");
                $this->line("   • Plan creado: {$planData['name']}");
                $this->line("   • Suscripción: Activa");
                $this->line("   • Usuario marcado como importado");

                Log::info('Importación exitosa con sandbox', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'plan_name' => $planData['name'],
                    'customer_oid' => $customerOid,
                    'result' => $result
                ]);

                return 0;
            } else {
                throw new \Exception('No se pudo crear la configuración completa del cliente');
            }

        } catch (\Exception $e) {
            $user->markAsFailed($e->getMessage());
            
            $this->error("\n❌ Error en la importación:");
            $this->line("   • Error: {$e->getMessage()}");
            $this->line("   • Usuario marcado como fallido");
            
            Log::error('Error en importación con sandbox', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tags' => $user->tags,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }
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
