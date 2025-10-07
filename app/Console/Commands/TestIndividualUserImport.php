<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MissingUser;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class TestIndividualUserImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:individual-user-import {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la importación individual de usuarios con plan y suscripción';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Probando importación individual de usuarios con plan y suscripción');
        $this->line('==============================================================');

        $baremetricsService = new BaremetricsService();
        
        // Verificar configuración
        $this->info("\n📋 Configuración actual:");
        $this->line("   • Entorno: " . ($baremetricsService->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("   • Base URL: " . $baremetricsService->getBaseUrl());
        $this->line("   • API Key: " . substr($baremetricsService->getApiKey(), 0, 10) . '...');

        // Obtener usuario para probar
        $userId = $this->argument('user_id');
        
        if ($userId) {
            $user = MissingUser::find($userId);
            if (!$user) {
                $this->error("❌ No se encontró el usuario con ID: {$userId}");
                return 1;
            }
        } else {
            $user = MissingUser::where('import_status', 'pending')->first();
            if (!$user) {
                $this->error("❌ No hay usuarios pendientes para probar");
                return 1;
            }
        }

        $this->info("\n👤 Usuario seleccionado para prueba:");
        $this->line("   • ID: {$user->id}");
        $this->line("   • Email: {$user->email}");
        $this->line("   • Nombre: {$user->name}");
        $this->line("   • Tags: {$user->tags}");
        $this->line("   • Estado: {$user->import_status}");

        // Determinar plan basado en tags
        $planData = $this->determinePlanFromTags($user->tags);
        
        $this->info("\n📦 Plan que se creará:");
        $this->line("   • Nombre: {$planData['name']}");
        $this->line("   • Intervalo: {$planData['interval']}");
        $this->line("   • Cantidad: {$planData['interval_count']}");
        $this->line("   • Precio: \${$planData['amount']} {$planData['currency']}");

        if (!$this->confirm('¿Continuar con la prueba de importación?')) {
            $this->info('Prueba cancelada por el usuario');
            return 0;
        }

        try {
            $this->info("\n🔄 Iniciando importación...");
            
            // Marcar como importando
            $user->markAsImporting();
            $this->line("   ✅ Usuario marcado como 'importando'");

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

            $this->line("   📝 Datos del cliente preparados");
            $this->line("   📝 Datos de suscripción preparados");

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

                Log::info('Prueba de importación individual exitosa', [
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
            
            Log::error('Error en prueba de importación individual', [
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
            // Plan por defecto si no hay tags
            return [
                'name' => 'creetelo_mensual',
                'interval' => 'month',
                'interval_count' => 1,
                'amount' => 0, // Precio por defecto
                'currency' => 'usd',
                'oid' => 'plan_' . uniqid(),
            ];
        }

        $tagsArray = array_map('trim', explode(',', $tags));
        
        // Buscar tags específicos de suscripción
        foreach ($tagsArray as $tag) {
            $tag = strtolower($tag);
            
            if (strpos($tag, 'creetelo_anual') !== false || strpos($tag, 'créetelo_anual') !== false) {
                return [
                    'name' => 'creetelo_anual',
                    'interval' => 'year',
                    'interval_count' => 1,
                    'amount' => 0, // Precio por defecto
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
            
            if (strpos($tag, 'creetelo_mensual') !== false || strpos($tag, 'créetelo_mensual') !== false) {
                return [
                    'name' => 'creetelo_mensual',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => 0, // Precio por defecto
                    'currency' => 'usd',
                    'oid' => 'plan_' . uniqid(),
                ];
            }
        }

        // Si no encuentra tags específicos, usar el primer tag como nombre del plan
        $firstTag = trim($tagsArray[0]);
        $interval = 'month'; // Por defecto mensual
        
        if (strpos($firstTag, 'anual') !== false || strpos($firstTag, 'year') !== false) {
            $interval = 'year';
        }

        return [
            'name' => $firstTag,
            'interval' => $interval,
            'interval_count' => 1,
            'amount' => 0, // Precio por defecto
            'currency' => 'usd',
            'oid' => 'plan_' . uniqid(),
        ];
    }
}
