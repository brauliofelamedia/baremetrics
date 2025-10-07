<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateUser extends Command
{
    protected $signature = 'baremetrics:cleanup-duplicate-user {email}';
    protected $description = 'Elimina entradas duplicadas de un usuario en Baremetrics y lo recrea con datos correctos';

    public function handle()
    {
        $email = $this->argument('email');
        $this->info("🧹 Limpiando entradas duplicadas para: {$email}");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Obtener datos reales del usuario desde GHL
            $this->info("📡 Obteniendo datos reales desde GHL...");
            $ghlResponse = $ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("❌ No se encontró el usuario en GHL");
                return;
            }

            $contact = $ghlResponse['contacts'][0];
            if (!$contact) {
                $this->error("❌ No se pudo obtener datos del contacto");
                return;
            }
            $realName = trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
            $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
            
            $this->info("👤 Nombre real: {$realName}");
            $this->info("📅 Fecha original: {$originalDate}");

            // 2. Buscar todas las entradas del usuario en Baremetrics
            $this->info("🔍 Buscando entradas existentes en Baremetrics...");
            $customers = $baremetricsService->getCustomers($sourceId);
            
            $userCustomers = [];
            if ($customers && isset($customers['customers'])) {
                foreach ($customers['customers'] as $customer) {
                    if (strtolower($customer['email']) === strtolower($email)) {
                        $userCustomers[] = $customer;
                    }
                }
            }

            if (empty($userCustomers)) {
                $this->warn("⚠️ No se encontraron entradas del usuario en Baremetrics");
                return;
            }

            $this->info("📋 Encontradas " . count($userCustomers) . " entradas del usuario");

            // 3. Para cada cliente encontrado, eliminar suscripciones y luego el cliente
            foreach ($userCustomers as $customer) {
                $customerOid = $customer['oid'];
                $this->info("🗑️ Procesando cliente: {$customerOid}");

                // Buscar suscripciones del cliente
                $subscriptions = $baremetricsService->getSubscriptions($sourceId);
                $customerSubscriptions = [];
                
                if ($subscriptions && isset($subscriptions['subscriptions'])) {
                    foreach ($subscriptions['subscriptions'] as $subscription) {
                        // Verificar diferentes posibles nombres de campo para customer_oid
                        $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                 $subscription['customer']['oid'] ?? 
                                                 $subscription['customerOid'] ?? 
                                                 null;
                        
                        if ($subscriptionCustomerOid === $customerOid) {
                            $customerSubscriptions[] = $subscription;
                        }
                    }
                }

                $this->info("📋 Encontradas " . count($customerSubscriptions) . " suscripciones para este cliente");

                // Eliminar suscripciones
                foreach ($customerSubscriptions as $subscription) {
                    $subscriptionOid = $subscription['oid'];
                    $this->info("🗑️ Eliminando suscripción: {$subscriptionOid}");
                    
                    $deleteResult = $baremetricsService->deleteSubscription($sourceId, $subscriptionOid);
                    if ($deleteResult) {
                        $this->info("✅ Suscripción eliminada: {$subscriptionOid}");
                    } else {
                        $this->error("❌ Error eliminando suscripción: {$subscriptionOid}");
                    }
                }

                // Eliminar cliente
                $this->info("🗑️ Eliminando cliente: {$customerOid}");
                $deleteCustomerResult = $baremetricsService->deleteCustomer($sourceId, $customerOid);
                
                if ($deleteCustomerResult) {
                    $this->info("✅ Cliente eliminado: {$customerOid}");
                } else {
                    $this->error("❌ Error eliminando cliente: {$customerOid}");
                }
            }

            // 4. Crear nuevo cliente con datos correctos
            $this->info("✨ Creando nuevo cliente con datos correctos...");
            
            $customerData = [
                'name' => $realName ?: 'Usuario GHL',
                'email' => $email,
                'company' => $contact['companyName'] ?? null,
                'notes' => 'Cliente migrado desde GHL - ' . date('Y-m-d H:i:s'),
                'oid' => 'cust_' . uniqid(),
            ];

            $newCustomer = $baremetricsService->createCustomer($customerData, $sourceId);
            
            if (!$newCustomer) {
                $this->error("❌ Error creando nuevo cliente");
                return;
            }

            $newCustomerOid = $newCustomer['customer']['oid'] ?? $newCustomer['oid'] ?? null;
            $this->info("✅ Nuevo cliente creado: {$newCustomerOid}");

            // 5. Determinar plan basado en tags de GHL
            $planData = $this->determinePlanFromTags($contact);
            if (!$planData) {
                $this->warn("⚠️ No se pudo determinar el plan, usando creetelo_mensual por defecto");
                $planData = [
                    'name' => 'creetelo_mensual',
                    'oid' => '1759521305199'
                ];
            }

            $this->info("📋 Plan asignado: {$planData['name']}");

            // 6. Crear suscripción con fecha original
            $this->info("📅 Creando suscripción con fecha original...");
            
            $subscriptionData = [
                'customer_oid' => $newCustomerOid,
                'plan_oid' => $planData['oid'],
                'status' => 'active',
                'oid' => 'sub_' . uniqid(),
            ];

            // Si tenemos fecha original, establecerla
            if ($originalDate) {
                try {
                    $startDate = new \DateTime($originalDate);
                    $subscriptionData['started_at'] = $startDate->getTimestamp();
                    $this->info("📅 Fecha de inicio establecida: " . $subscriptionData['started_at'] . " (timestamp)");
                } catch (\Exception $e) {
                    $this->warn("⚠️ Error procesando fecha original: " . $e->getMessage());
                }
            }

            $this->info("📋 Datos de suscripción: " . json_encode($subscriptionData));
            $newSubscription = $baremetricsService->createSubscription($subscriptionData, $sourceId);
            
            if (!$newSubscription) {
                $this->error("❌ Error creando nueva suscripción");
                return;
            }

            $subscriptionOid = $newSubscription['oid'] ?? 
                             $newSubscription['subscription']['oid'] ?? 
                             $newSubscription['event']['subscription_oid'] ?? 
                             null;

            $this->info("✅ Nueva suscripción creada: {$subscriptionOid}");

            // 7. Marcar como migrado en base de datos local
            $this->markAsMigrated($email);

            $this->info("🎉 ¡Limpieza completada exitosamente!");
            $this->info("👤 Cliente: {$newCustomerOid}");
            $this->info("📋 Suscripción: {$subscriptionOid}");
            $this->info("📅 Fecha original respetada: " . ($originalDate ?: 'No disponible'));

        } catch (\Exception $e) {
            $this->error("❌ Error durante la limpieza: " . $e->getMessage());
            Log::error('Error en cleanup de usuario duplicado', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function determinePlanFromTags($contact): ?array
    {
        $tags = $contact['tags'] ?? [];
        $tagNames = array_column($tags, 'name');
        
        $planMapping = [
            'creetelo_mensual' => ['name' => 'creetelo_mensual', 'oid' => '1759521305199'],
            'créetelo_mensual' => ['name' => 'créetelo_mensual', 'oid' => '1759521318146'],
            'creetelo_anual' => ['name' => 'creetelo_anual', 'oid' => '1759827004232'],
            'créetelo_anual' => ['name' => 'créetelo_anual', 'oid' => '1759827093640'],
        ];

        foreach ($planMapping as $tagName => $planData) {
            if (in_array($tagName, $tagNames)) {
                return $planData;
            }
        }

        return null;
    }

    private function markAsMigrated(string $email): void
    {
        try {
            // Buscar el usuario en la tabla ghl_comparison
            $user = \DB::table('ghl_comparison')
                ->where('email', $email)
                ->first();

            if ($user) {
                \DB::table('ghl_comparison')
                    ->where('email', $email)
                    ->update([
                        'migrated' => true,
                        'migrated_at' => now(),
                        'updated_at' => now()
                    ]);
                
                $this->info("✅ Usuario marcado como migrado en base de datos local");
            } else {
                $this->warn("⚠️ Usuario no encontrado en tabla ghl_comparison");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️ Error marcando como migrado: " . $e->getMessage());
        }
    }
}
