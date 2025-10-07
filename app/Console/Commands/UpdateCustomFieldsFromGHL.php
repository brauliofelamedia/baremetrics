<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class UpdateCustomFieldsFromGHL extends Command
{
    protected $signature = 'baremetrics:update-custom-fields {email}';
    protected $description = 'Actualiza custom fields de un usuario en Baremetrics con datos de GHL';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("📋 Actualizando custom fields para: {$email}");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Buscar cliente en Baremetrics
            $this->info("🔍 Buscando cliente en Baremetrics...");
            $customers = $baremetricsService->getCustomers($sourceId);
            
            $userCustomer = null;
            if ($customers && isset($customers['customers'])) {
                foreach ($customers['customers'] as $customer) {
                    if (strtolower($customer['email']) === strtolower($email)) {
                        $userCustomer = $customer;
                        break;
                    }
                }
            }

            if (!$userCustomer) {
                $this->error("❌ No se encontró el cliente en Baremetrics");
                return;
            }

            $customerOid = $userCustomer['oid'];
            $this->info("✅ Cliente encontrado: {$customerOid}");

            // 2. Obtener datos del usuario desde GHL
            $this->info("📡 Obteniendo datos desde GHL...");
            $ghlResponse = $ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("❌ No se encontró el usuario en GHL");
                return;
            }

            $contact = $ghlResponse['contacts'][0];
            $contactId = $contact['id'];
            
            $this->info("🆔 Contact ID: {$contactId}");

            // 3. Obtener información de suscripciones y pagos
            $this->info("🔍 Obteniendo información de suscripciones y pagos...");
            
            // Obtener suscripciones
            $subscriptions = $ghlService->getSubscriptionStatusByContact($contactId);
            $subscriptionInfo = null;
            if ($subscriptions) {
                $this->info("📋 Suscripciones encontradas: " . ($subscriptions['total_subscriptions'] ?? 0));
                $subscriptionInfo = $subscriptions;
            }

            // Obtener pagos/transacciones
            $payments = $ghlService->getContactPayments($contactId);
            $totalPaid = 0;
            $lastPayment = null;
            $lastPaymentDate = null;
            
            if ($payments) {
                $this->info("💳 Pagos encontrados: " . count($payments));
                
                // Calcular total pagado y último pago
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

            // 4. Preparar datos para actualizar en Baremetrics
            $this->info("📋 Preparando datos para actualizar...");
            
            $ghlData = [
                'subscriptions' => $subscriptionInfo ? json_encode($subscriptionInfo) : null,
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
            ];

            // 5. Actualizar custom fields en Baremetrics
            $this->info("🔄 Actualizando custom fields en Baremetrics...");
            
            $updateResult = $baremetricsService->updateCustomerAttributes($customerOid, $ghlData);
            
            if ($updateResult) {
                $this->info("✅ Custom fields actualizados exitosamente");
                
                // Mostrar resumen de datos actualizados
                $this->info("📊 Resumen de datos actualizados:");
                $this->info("   • Total pagado: $" . number_format($totalPaid, 2));
                $this->info("   • Pagos procesados: " . count($payments ?: []));
                $this->info("   • Suscripciones: " . ($subscriptionInfo['total_subscriptions'] ?? 0));
                $this->info("   • Tags: " . count($contact['tags'] ?? []));
                $this->info("   • Custom fields: " . count($contact['customFields'] ?? []));
                
            } else {
                $this->error("❌ Error actualizando custom fields");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante la actualización: " . $e->getMessage());
            Log::error('Error actualizando custom fields', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
