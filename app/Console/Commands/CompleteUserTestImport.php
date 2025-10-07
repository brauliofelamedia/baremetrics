<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CompleteUserTestImport extends Command
{
    protected $signature = 'baremetrics:complete-test-import {email}';
    protected $description = 'Prueba completa: elimina y reimporta usuario con todos los datos y custom fields';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🧪 PRUEBA COMPLETA para: {$email}");
        $this->warn("⚠️  Este comando ELIMINARÁ y RECREARÁ el usuario completamente");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Obtener datos del usuario desde GHL PRIMERO
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
            }

            // Obtener pagos/transacciones
            $payments = $ghlService->getContactPayments($contactId);
            $totalPaid = 0;
            $lastPayment = null;
            $lastPaymentDate = null;
            
            if ($payments) {
                $this->info("💳 Pagos encontrados: " . count($payments));
                
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

            // 3. ELIMINAR entradas existentes en Baremetrics
            $this->info("🗑️ Eliminando entradas existentes en Baremetrics...");
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
                $this->info("📋 Encontradas " . count($userCustomers) . " entradas del usuario");

                // Para cada cliente encontrado, eliminar suscripciones y luego el cliente
                foreach ($userCustomers as $customer) {
                    $customerOid = $customer['oid'];
                    $this->info("🗑️ Procesando cliente: {$customerOid}");

                    // Buscar suscripciones del cliente
                    $subscriptions = $baremetricsService->getSubscriptions($sourceId);
                    $customerSubscriptions = [];
                    
                    if ($subscriptions && isset($subscriptions['subscriptions'])) {
                        foreach ($subscriptions['subscriptions'] as $subscription) {
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
            } else {
                $this->info("ℹ️ No se encontraron entradas existentes del usuario");
            }

            // 4. CREAR nuevo cliente con datos correctos
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

            // 7. Actualizar TODOS los custom fields incluyendo GHL: Migrate GHL = true
            $this->info("📋 Actualizando TODOS los custom fields...");
            
            // Preparar datos completos de GHL para actualizar en Baremetrics
            $ghlData = [
                // Datos básicos
                'subscriptions' => $subscriptions ? json_encode($subscriptions) : null,
                'payments' => $payments ? json_encode($payments) : null,
                'total_paid' => $totalPaid,
                'last_payment_date' => $lastPaymentDate,
                'last_payment_amount' => $lastPayment['amount'] ?? null,
                'contact_id' => $contactId,
                'tags' => json_encode($contact['tags'] ?? []),
                'custom_fields' => json_encode($contact['customFields'] ?? []),
                'phone' => $contact['phone'] ?? null,
                'company_name' => $contact['companyName'] ?? null,
                'address' => $contact['address'] ?? null,
                'city' => $contact['city'] ?? null,
                'state' => $contact['state'] ?? null,
                'country' => $contact['country'] ?? null,
                'postal_code' => $contact['postalCode'] ?? null,
                'date_added' => $contact['dateAdded'] ?? null,
                'date_updated' => $contact['dateUpdated'] ?? null,
                
                // IMPORTANTE: Marcar como migrado desde GHL
                'GHL: Migrate GHL' => true,
            ];

            $updateResult = $baremetricsService->updateCustomerAttributes($newCustomerOid, $ghlData);
            
            if ($updateResult) {
                $this->info("✅ Custom fields actualizados exitosamente");
                $this->info("✅ GHL: Migrate GHL = true (marcado como migrado)");
            } else {
                $this->error("❌ Error actualizando custom fields");
            }

            // 8. Resumen final completo
            $this->info("🎉 ¡PRUEBA COMPLETA EXITOSA!");
            $this->info("👤 Cliente: {$newCustomerOid}");
            $this->info("📋 Suscripción: {$subscriptionOid}");
            $this->info("📅 Fecha original respetada: " . ($originalDate ?: 'No disponible'));
            $this->info("📋 Plan: {$planData['name']}");
            
            if ($payments) {
                $this->info("💳 Total pagado: $" . number_format($totalPaid, 2));
                $this->info("📊 Pagos procesados: " . count($payments));
            }
            
            if ($subscriptions) {
                $this->info("📋 Suscripciones encontradas: " . ($subscriptions['total_subscriptions'] ?? 0));
            }
            
            $this->info("✅ GHL: Migrate GHL = true (marcado como migrado desde GHL)");
            $this->info("📊 Custom fields actualizados: " . count($ghlData));

        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba completa: " . $e->getMessage());
            Log::error('Error en prueba completa de importación', [
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
