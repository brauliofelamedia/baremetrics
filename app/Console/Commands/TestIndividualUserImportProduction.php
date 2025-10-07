<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use App\Models\MissingUser;

class TestIndividualUserImportProduction extends Command
{
    protected $signature = 'baremetrics:test-individual-import-production {email}';
    protected $description = 'Probar importación individual de usuario a Baremetrics en producción';

    public function handle()
    {
        $email = $this->argument('email');
        
        // Configurar entorno de producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();

        $this->info("🧪 Prueba de importación individual en PRODUCCIÓN");
        $this->line("===============================================");
        $this->line("Usuario: {$email}");
        $this->line("Entorno: " . ($baremetricsService->isSandbox() ? 'Sandbox' : 'Producción'));
        $this->line("Base URL: " . $baremetricsService->getBaseUrl());

        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8'; // Source manual de baremetrics

        // Paso 1: Obtener información del usuario desde GHL
        $this->info("\n🔍 Obteniendo información del usuario desde GHL...");
        $contactsResponse = $ghlService->getContacts($email);
        
        if (!$contactsResponse || empty($contactsResponse['contacts'])) {
            $this->error("❌ Usuario no encontrado en GHL: {$email}");
            return 1;
        }

        $user = $contactsResponse['contacts'][0]; // Tomar el primer contacto encontrado

        $this->info("✅ Usuario encontrado en GHL:");
        $this->line("   • Nombre: " . ($user['name'] ?? 'No especificado'));
        $this->line("   • Email: " . ($user['email'] ?? 'No especificado'));
        $this->line("   • Teléfono: " . ($user['phone'] ?? 'No especificado'));
        $this->line("   • Tags: " . implode(', ', $user['tags'] ?? []));

        // Paso 2: Determinar el plan basado en los tags
        $this->info("\n📋 Determinando plan basado en tags...");
        $planData = $this->determinePlanFromTags($user['tags'] ?? []);
        
        if (!$planData) {
            $this->error("❌ No se pudo determinar el plan para este usuario");
            $this->line("   Tags disponibles: " . implode(', ', $user['tags'] ?? []));
            return 1;
        }

        $this->info("✅ Plan determinado:");
        $this->line("   • Nombre: {$planData['name']}");
        $this->line("   • Intervalo: {$planData['interval']}");
        $this->line("   • Precio: $" . ($planData['amounts'][0]['amount'] / 100) . " {$planData['amounts'][0]['currency']}");

        // Paso 3: Obtener el OID del plan existente
        $this->info("\n🔍 Obteniendo OID del plan existente...");
        $planOid = $this->getExistingPlanOid($planData['name'], $sourceId, $baremetricsService);
        
        if (!$planOid) {
            $this->error("❌ No se pudo encontrar el plan: {$planData['name']}");
            return 1;
        }
        
        $this->info("✅ Plan existente encontrado: {$planOid}");

        // Paso 4: Crear el cliente
        $this->info("\n👤 Creando cliente en Baremetrics...");
        $customerData = [
            'name' => $user['name'] ?? 'Usuario GHL',
            'email' => $user['email'],
            'company' => $user['company'] ?? null,
            'notes' => "Importado desde GHL - Tags: " . implode(', ', $user['tags'] ?? []),
            'oid' => 'cust_' . uniqid(),
        ];

        $customer = $baremetricsService->createCustomer($customerData, $sourceId);
        
        if (!$customer) {
            $this->error("❌ Error al crear el cliente");
            return 1;
        }

        $customerOid = $customer['customer']['oid'];
        $this->info("✅ Cliente creado exitosamente: {$customerOid}");

        // Paso 5: Crear la suscripción
        $this->info("\n📅 Creando suscripción...");
        $subscriptionData = [
            'customer_oid' => $customerOid,
            'plan_oid' => $planOid,
            'started_at' => now()->timestamp,
            'status' => 'active',
            'notes' => "Suscripción creada automáticamente desde GHL"
        ];

        $subscription = $baremetricsService->createSubscription($subscriptionData, $sourceId);
        
        if (!$subscription) {
            $this->error("❌ Error al crear la suscripción");
            return 1;
        }

        // La respuesta puede tener diferentes estructuras, vamos a manejarla de forma segura
        $subscriptionOid = $subscription['oid'] ?? 
                          $subscription['subscription']['oid'] ?? 
                          $subscription['event']['subscription_oid'] ?? 
                          null;
        
        if (!$subscriptionOid) {
            $this->error("❌ No se pudo obtener el OID de la suscripción");
            $this->line("Respuesta recibida: " . json_encode($subscription));
            return 1;
        }

        $this->info("✅ Suscripción creada exitosamente: {$subscriptionOid}");

        // Paso 6: Marcar como migrado en la base de datos local
        $this->info("\n📝 Marcando usuario como migrado...");
        $missingUser = MissingUser::where('email', $email)->first();
        
        if ($missingUser) {
            $missingUser->update([
                'status' => 'imported',
                'imported_at' => now(),
                'baremetrics_customer_id' => $customerOid,
                'baremetrics_subscription_id' => $subscriptionOid
            ]);
            $this->info("✅ Usuario marcado como migrado en la base de datos local");
        } else {
            $this->warn("⚠️ Usuario no encontrado en la tabla de usuarios faltantes");
        }

        // Resumen final
        $this->info("\n🎉 ¡IMPORTACIÓN COMPLETADA EXITOSAMENTE!");
        $this->line("=====================================");
        $this->line("👤 Cliente: {$customerOid}");
        $this->line("📋 Plan: {$planOid} ({$planData['name']})");
        $this->line("📅 Suscripción: {$subscriptionOid}");
        $this->line("📧 Email: {$email}");
        $this->line("🏷️ Tags: " . implode(', ', $user['tags'] ?? []));
        
        $this->info("\n✅ El usuario {$email} ha sido importado exitosamente a Baremetrics en PRODUCCIÓN");
        
        return 0;
    }

    /**
     * Determinar el plan basado en los tags del usuario
     */
    private function determinePlanFromTags(array $tags): ?array
    {
        $tagString = implode(',', $tags);
        
        // Mapeo de tags a planes
        $planMappings = [
            'creetelo_mensual' => [
                'name' => 'creetelo_mensual',
                'interval' => 'month',
                'interval_count' => 1,
                'amounts' => [
                    [
                        'currency' => 'USD',
                        'amount' => 3900, // $39.00
                        'symbol' => '$',
                        'symbol_right' => false
                    ]
                ],
                'active' => true
            ],
            'créetelo_mensual' => [
                'name' => 'créetelo_mensual',
                'interval' => 'month',
                'interval_count' => 1,
                'amounts' => [
                    [
                        'currency' => 'USD',
                        'amount' => 3900, // $39.00
                        'symbol' => '$',
                        'symbol_right' => false
                    ]
                ],
                'active' => true
            ],
            'creetelo_anual' => [
                'name' => 'creetelo_anual',
                'interval' => 'year',
                'interval_count' => 1,
                'amounts' => [
                    [
                        'currency' => 'USD',
                        'amount' => 39000, // $390.00
                        'symbol' => '$',
                        'symbol_right' => false
                    ]
                ],
                'active' => true
            ],
            'créetelo_anual' => [
                'name' => 'créetelo_anual',
                'interval' => 'year',
                'interval_count' => 1,
                'amounts' => [
                    [
                        'currency' => 'USD',
                        'amount' => 39000, // $390.00
                        'symbol' => '$',
                        'symbol_right' => false
                    ]
                ],
                'active' => true
            ]
        ];

        // Buscar el primer tag que coincida
        foreach ($planMappings as $tag => $planData) {
            if (strpos($tagString, $tag) !== false) {
                return $planData;
            }
        }

        return null;
    }

    /**
     * Obtener el OID de un plan existente por nombre
     */
    private function getExistingPlanOid(string $planName, string $sourceId, BaremetricsService $service): ?string
    {
        // Mapeo directo de nombres a OIDs conocidos
        $planOids = [
            'creetelo_mensual' => '1759521305199',
            'créetelo_mensual' => '1759521318146',
            'creetelo_anual' => '1759827004232',
            'créetelo_anual' => '1759827093640'
        ];

        if (isset($planOids[$planName])) {
            return $planOids[$planName];
        }

        // Si no está en el mapeo, intentar buscar en la API
        $plans = $service->getPlans($sourceId);
        if ($plans && isset($plans['plans'])) {
            foreach ($plans['plans'] as $plan) {
                if ($plan['name'] === $planName) {
                    return $plan['oid'];
                }
            }
        }

        return null;
    }
}
