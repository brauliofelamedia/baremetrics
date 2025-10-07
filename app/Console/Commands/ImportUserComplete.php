<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class ImportUserComplete extends Command
{
    protected $signature = 'baremetrics:import-user-complete {email}';
    protected $description = 'Importación completa de usuario desde GHL a Baremetrics SIN eliminar datos existentes';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🚀 IMPORTACIÓN COMPLETA para: {$email}");
        $this->info("✅ Este comando NO eliminará datos existentes - solo creará/actualizará");

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

            // 3. VERIFICAR si el usuario ya existe en Baremetrics
            $this->info("🔍 Verificando si el usuario ya existe en Baremetrics...");
            $customers = $baremetricsService->getCustomers($sourceId);
            
            $existingCustomer = null;
            if ($customers && isset($customers['customers'])) {
                foreach ($customers['customers'] as $customer) {
                    if (strtolower($customer['email']) === strtolower($email)) {
                        $existingCustomer = $customer;
                        break;
                    }
                }
            }

            $customerOid = null;
            
            if ($existingCustomer) {
                $this->info("✅ Usuario ya existe en Baremetrics: " . $existingCustomer['oid']);
                $customerOid = $existingCustomer['oid'];
                $this->info("📝 Usando cliente existente, solo actualizando campos personalizados");
            } else {
                // 4. CREAR nuevo cliente si no existe
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

                $customerOid = $newCustomer['customer']['oid'] ?? $newCustomer['oid'] ?? null;
                $this->info("✅ Nuevo cliente creado: {$customerOid}");
            }

            // 5. Determinar plan basado en tags de GHL
            $planData = $this->determinePlanFromTags($contact);
            if (!$planData) {
                $this->warn("⚠️ No se pudo determinar el plan, usando creetelo_mensual por defecto");
                $this->info("📋 Tags del usuario: " . json_encode($contact['tags'] ?? []));
                $planData = [
                    'name' => 'creetelo_mensual',
                    'oid' => '1759521305199'
                ];
            }

            $this->info("📋 Plan asignado: {$planData['name']}");

            // 6. Verificar si ya tiene suscripción activa
            $this->info("🔍 Verificando suscripciones existentes...");
            $subscriptions = $baremetricsService->getSubscriptions($sourceId);
            $existingSubscription = null;
            
            if ($subscriptions && isset($subscriptions['subscriptions'])) {
                foreach ($subscriptions['subscriptions'] as $subscription) {
                    $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                             $subscription['customer']['oid'] ?? 
                                             $subscription['customerOid'] ?? 
                                             null;
                    
                    if ($subscriptionCustomerOid === $customerOid) {
                        $existingSubscription = $subscription;
                        break;
                    }
                }
            }

            if ($existingSubscription) {
                $this->info("✅ Usuario ya tiene suscripción activa: " . $existingSubscription['oid']);
                $this->info("📋 Plan actual: " . ($existingSubscription['plan']['name'] ?? 'N/A'));
            } else {
                // 7. Crear suscripción con fecha original
                $this->info("📅 Creando nueva suscripción...");
                
                $subscriptionData = [
                    'customer_oid' => $customerOid,
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
            }

            // 8. Actualizar TODOS los custom fields incluyendo GHL: Migrate GHL = true
            $this->info("📋 Actualizando TODOS los custom fields...");
            
            // Preparar datos esenciales de GHL para actualizar en Baremetrics (mínimos para evitar problemas de memoria)
            $ghlData = [
                // Datos esenciales únicamente
                'total_paid' => $totalPaid,
                'last_payment_date' => $lastPaymentDate,
                'last_payment_amount' => $lastPayment['amount'] ?? null,
                'contact_id' => $contactId,
                'phone' => $contact['phone'] ?? null,
                'company_name' => $contact['companyName'] ?? null,
                'date_added' => $contact['dateAdded'] ?? null,
                
                // IMPORTANTE: Marcar como migrado desde GHL
                'GHL: Migrate GHL' => true,
            ];

            $updateResult = $baremetricsService->updateCustomerAttributes($customerOid, $ghlData);
            
            if ($updateResult) {
                $this->info("✅ Custom fields actualizados exitosamente");
                $this->info("✅ GHL: Migrate GHL = true (marcado como migrado)");
            } else {
                $this->error("❌ Error actualizando custom fields");
            }

            // 9. Resumen final completo
            $this->info("🎉 ¡IMPORTACIÓN COMPLETA EXITOSA!");
            $this->info("👤 Cliente: {$customerOid}");
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
            $this->error("❌ Error durante la importación completa: " . $e->getMessage());
            Log::error('Error en importación completa de usuario', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function determinePlanFromTags($contact): ?array
    {
        $tags = $contact['tags'] ?? [];
        $tagNames = [];
        
        // Extraer nombres de tags correctamente
        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $tagNames[] = $tag;
            } elseif (is_array($tag) && isset($tag['name'])) {
                $tagNames[] = $tag['name'];
            }
        }
        
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
