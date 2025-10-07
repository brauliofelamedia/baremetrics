<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class EnhancedUserImportProduction extends Command
{
    protected $signature = 'baremetrics:enhanced-import-production {email} {--update-fields : Actualizar custom fields en Baremetrics}';
    protected $description = 'Importa usuario a Baremetrics con investigación de renovaciones y actualización de custom fields';

    public function handle()
    {
        $email = $this->argument('email');
        $updateFields = $this->option('update-fields');
        
        $this->info("🚀 Importación mejorada para: {$email}");
        if ($updateFields) {
            $this->info("📋 Actualizando custom fields en Baremetrics");
        }

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Obtener datos del usuario desde GHL
            $this->info("📡 Obteniendo datos desde GHL...");
            $ghlResponse = $ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("❌ No se encontró el usuario en GHL");
                return;
            }

            $contact = $ghlResponse['contacts'][0];
            $contactId = $contact['id'];
            $realName = trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
            $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
            
            $this->info("👤 Nombre real: {$realName}");
            $this->info("📅 Fecha original: {$originalDate}");
            $this->info("🆔 Contact ID: {$contactId}");

            // 2. Investigar renovaciones y pagos
            $this->info("🔍 Investigando renovaciones y pagos...");
            
            // Obtener suscripciones
            $subscriptions = $ghlService->getSubscriptionStatusByContact($contactId);
            if ($subscriptions) {
                $this->info("📋 Suscripciones encontradas: " . ($subscriptions['total_subscriptions'] ?? 0));
                if (isset($subscriptions['subscription'])) {
                    $sub = $subscriptions['subscription'];
                    $this->info("   • Estado: " . ($sub['status'] ?? 'N/A'));
                    $this->info("   • Próximo pago: " . ($sub['nextBillingDate'] ?? 'N/A'));
                    $this->info("   • Monto: $" . ($sub['amount'] ?? 'N/A'));
                }
            }

            // Obtener pagos/transacciones
            $payments = $ghlService->getContactPayments($contactId);
            if ($payments) {
                $this->info("💳 Pagos encontrados: " . count($payments));
                
                // Calcular total pagado y último pago
                $totalPaid = 0;
                $lastPayment = null;
                $lastPaymentDate = null;
                
                foreach ($payments as $payment) {
                    if (isset($payment['amount'])) {
                        $totalPaid += floatval($payment['amount']);
                    }
                    
                    $paymentDate = $payment['createdAt'] ?? $payment['date'] ?? null;
                    if ($paymentDate && (!$lastPaymentDate || strtotime($paymentDate) > strtotime($lastPaymentDate))) {
                        $lastPayment = $payment;
                        $lastPaymentDate = $paymentDate;
                    }
                }
                
                $this->info("   • Total pagado: $" . number_format($totalPaid, 2));
                if ($lastPayment) {
                    $this->info("   • Último pago: $" . ($lastPayment['amount'] ?? 'N/A') . " el " . $lastPaymentDate);
                }
            }

            // 3. Buscar entradas existentes en Baremetrics
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

            if (!empty($userCustomers)) {
                $this->warn("⚠️ Encontradas " . count($userCustomers) . " entradas existentes");
                $this->info("💡 Usa 'baremetrics:cleanup-duplicate-user {$email}' para limpiar duplicados");
                return;
            }

            // 4. Crear nuevo cliente con datos correctos
            $this->info("✨ Creando nuevo cliente...");
            
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
            $this->info("📅 Creando suscripción...");
            
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

            // 7. Actualizar custom fields si se solicita
            if ($updateFields) {
                $this->info("📋 Actualizando custom fields en Baremetrics...");
                
                // Preparar datos de GHL para actualizar en Baremetrics
                $ghlData = [
                    'subscriptions' => $subscriptions ? json_encode($subscriptions) : null,
                    'payments' => $payments ? json_encode($payments) : null,
                    'total_paid' => $totalPaid ?? 0,
                    'last_payment_date' => $lastPaymentDate ?? null,
                    'last_payment_amount' => $lastPayment['amount'] ?? null,
                    'contact_id' => $contactId,
                    'tags' => json_encode($contact['tags'] ?? []),
                ];

                $updateResult = $baremetricsService->updateCustomerAttributes($newCustomerOid, $ghlData);
                
                if ($updateResult) {
                    $this->info("✅ Custom fields actualizados exitosamente");
                } else {
                    $this->warn("⚠️ Error actualizando custom fields");
                }
            }

            // 8. Resumen final
            $this->info("🎉 ¡Importación completada exitosamente!");
            $this->info("👤 Cliente: {$newCustomerOid}");
            $this->info("📋 Suscripción: {$subscriptionOid}");
            $this->info("📅 Fecha original respetada: " . ($originalDate ?: 'No disponible'));
            
            if ($payments) {
                $this->info("💳 Total pagado: $" . number_format($totalPaid, 2));
                $this->info("📊 Pagos procesados: " . count($payments));
            }
            
            if ($subscriptions) {
                $this->info("📋 Suscripciones encontradas: " . ($subscriptions['total_subscriptions'] ?? 0));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            Log::error('Error en importación mejorada', [
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
}
