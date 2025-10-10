<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FixSpecificUserById extends Command
{
    protected $signature = 'baremetrics:fix-user-by-id 
                           {identifier : ID del cliente en Baremetrics (ej: cust_68e55c311a2b9) o email (ej: usuario@email.com)}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}
                           {--skip-dates : Omitir corrección de fechas}
                           {--skip-fields : Omitir actualización de campos personalizados}
                           {--skip-coupons : Omitir actualización de cupones}';
    
    protected $description = 'Corrige los datos de un usuario específico usando su ID de Baremetrics o email';

    protected $baremetricsService;
    protected $ghlService;

    public function __construct(BaremetricsService $baremetricsService, GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
        $this->ghlService = $ghlService;
    }

    public function handle()
    {
        $identifier = $this->argument('identifier');
        $dryRun = $this->option('dry-run');
        $skipDates = $this->option('skip-dates');
        $skipFields = $this->option('skip-fields');
        $skipCoupons = $this->option('skip-coupons');

        $this->info("🔧 CORRECCIÓN DE USUARIO ESPECÍFICO");
        $this->info("====================================");
        $this->info("Identificador: {$identifier}");
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->info("Omitir fechas: " . ($skipDates ? 'Sí' : 'No'));
        $this->info("Omitir campos: " . ($skipFields ? 'Sí' : 'No'));
        $this->info("Omitir cupones: " . ($skipCoupons ? 'Sí' : 'No'));
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Determinar si el identificador es un email o un ID
            $customer = null;
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                // Es un email, buscar por email
                $this->info("📧 Identificador detectado como email, buscando cliente...");
                $customer = $this->findCustomerByEmail($identifier, $sourceId);
                if (!$customer) {
                    $this->error("❌ No se encontró el cliente con email: {$identifier}");
                    return 1;
                }
            } else {
                // Es un ID, buscar por ID
                $this->info("🆔 Identificador detectado como ID, buscando cliente...");
                $customer = $this->findCustomerById($identifier, $sourceId);
                if (!$customer) {
                    $this->error("❌ No se encontró el cliente con ID: {$identifier}");
                    return 1;
                }
            }

            $this->info("✅ Cliente encontrado: {$customer['oid']}");
            $this->info("📧 Email: " . ($customer['email'] ?? 'No disponible'));
            $this->info("👤 Nombre: " . ($customer['name'] ?? 'No disponible'));

            // 2. Obtener datos reales desde GHL usando el email
            $email = $customer['email'];
            if (!$email) {
                $this->error("❌ El cliente no tiene email asociado");
                return 1;
            }

            $ghlData = $this->getGHLData($email);
            if (!$ghlData) {
                $this->error("❌ No se encontraron datos en GHL para: {$email}");
                return 1;
            }

            $contact = $ghlData['contact'];
            $this->info("👤 Usuario GHL: {$contact['firstName']} {$contact['lastName']}");
            $this->info("📅 Fecha original: " . ($contact['dateAdded'] ?? 'No disponible'));

            // 3. Corregir fechas de suscripciones
            if (!$skipDates) {
                $this->info("📅 Corrigiendo fechas de suscripciones...");
                $this->fixSubscriptionDates($customer, $ghlData, $sourceId, $dryRun);
            } else {
                $this->info("⏭️ Omitiendo corrección de fechas");
            }

            // 4. Actualizar campos personalizados
            if (!$skipFields) {
                $this->info("📋 Actualizando campos personalizados...");
                $this->updateCustomFields($customer, $ghlData, $dryRun);
            } else {
                $this->info("⏭️ Omitiendo actualización de campos personalizados");
            }

            // 5. Actualizar cupones
            if (!$skipCoupons) {
                $this->info("🎫 Actualizando cupones...");
                $couponCode = $this->detectCouponFromGHL($ghlData);
                if ($couponCode) {
                    $this->info("🎫 Cupón detectado: {$couponCode}");
                    $this->updateCouponInBaremetrics($customer, $couponCode, $dryRun);
                } else {
                    $this->warn("⚠️ No se encontró cupón para este usuario");
                }
            } else {
                $this->info("⏭️ Omitiendo actualización de cupones");
            }

            // 6. Asignar suscripción al plan correcto con fecha de membresía
            $this->info("📋 Asignando suscripción al plan correcto...");
            $this->assignSubscriptionToPlan($customer, $ghlData, $sourceId, $dryRun);

            // 7. Actualizar campo GHL: Subscriptions en Baremetrics (NO en GHL)
            $this->info("📋 Actualizando campo GHL: Subscriptions en Baremetrics...");
            $this->updateGHLSubscriptionsFieldInBaremetrics($customer, $ghlData['latest_subscription'], $dryRun);

            $this->newLine();
            $this->info("🎉 ¡Corrección completada para el usuario {$customer['oid']}!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la corrección: " . $e->getMessage());
            Log::error('Error corrigiendo usuario', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Buscar cliente por ID en Baremetrics
     */
    private function findCustomerById(string $customerId, string $sourceId): ?array
    {
        try {
            // Buscar en la lista de clientes
            $customers = $this->baremetricsService->getCustomers($sourceId);
            
            if (!$customers || !isset($customers['customers'])) {
                return null;
            }

            foreach ($customers['customers'] as $customer) {
                if ($customer['oid'] === $customerId) {
                    return $customer;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error buscando cliente por ID', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Buscar cliente por email en Baremetrics
     */
    private function findCustomerByEmail(string $email, string $sourceId): ?array
    {
        try {
            // Buscar en la lista de clientes
            $customers = $this->baremetricsService->getCustomers($sourceId);
            
            if (!$customers || !isset($customers['customers'])) {
                return null;
            }

            foreach ($customers['customers'] as $customer) {
                if (isset($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
                    return $customer;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error buscando cliente por email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener datos reales desde GHL
     */
    private function getGHLData(string $email): ?array
    {
        try {
            $this->info("   🔍 Buscando en GHL: {$email}");
            
            // Primero intentar búsqueda exacta
            $this->info("   📋 Intentando búsqueda exacta...");
            $ghlResponse = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con contains
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->warn("   🔍 Búsqueda exacta falló, intentando con contains...");
                $ghlResponse = $this->ghlService->getContacts($email);
            }
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("   ❌ No se encontró el contacto en GHL con ningún método de búsqueda");
                $this->error("   📊 Respuesta recibida: " . json_encode($ghlResponse));
                return null;
            }

            $this->info("   ✅ Contacto encontrado en GHL!");
            $contact = $ghlResponse['contacts'][0];
            $this->info("   👤 Contacto ID: " . $contact['id']);
            
            // Obtener la última suscripción del usuario
            $this->info("   📋 Obteniendo última suscripción...");
            try {
                $latestSubscription = $this->ghlService->getMostRecentActiveSubscription($contact['id']);
                $this->info("   ✅ Última suscripción obtenida");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ Error obteniendo última suscripción: " . $e->getMessage());
                $latestSubscription = null;
            }
            
            // Obtener todas las suscripciones para el campo GHL: Subscriptions
            $this->info("   📋 Obteniendo todas las suscripciones...");
            try {
                $allSubscriptions = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
                $this->info("   ✅ Todas las suscripciones obtenidas");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ Error obteniendo todas las suscripciones: " . $e->getMessage());
                $allSubscriptions = null;
            }
            
            // Obtener información de membresía
            $this->info("   🏆 Obteniendo información de membresía...");
            try {
                $membership = $this->ghlService->getContactMembership($contact['id']);
                $this->info("   ✅ Información de membresía obtenida");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ Error obteniendo membresía: " . $e->getMessage());
                $membership = null;
            }
            
            // Obtener pagos
            $this->info("   💳 Obteniendo pagos...");
            try {
                $payments = $this->ghlService->getContactPayments($contact['id']);
                $this->info("   ✅ Pagos obtenidos");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ Error obteniendo pagos: " . $e->getMessage());
                $payments = null;
            }
            
            $this->info("   ✅ Todos los datos de GHL obtenidos exitosamente!");
            
            return [
                'contact' => $contact,
                'latest_subscription' => $latestSubscription,
                'all_subscriptions' => $allSubscriptions,
                'membership' => $membership,
                'payments' => $payments
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo datos de GHL', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Actualizar campos personalizados
     */
    private function updateCustomFields(array $customer, array $ghlData, bool $dryRun): bool
    {
        $contact = $ghlData['contact'];
        $latestSubscription = $ghlData['latest_subscription'];
        $allSubscriptions = $ghlData['all_subscriptions'];
        $membership = $ghlData['membership'];
        $payments = $ghlData['payments'];

        // Preparar datos actualizados
        $totalPaid = 0;
        $lastPayment = null;
        $lastPaymentDate = null;

        if ($payments && is_array($payments)) {
            foreach ($payments as $payment) {
                $amount = $payment['amount'] ?? 0;
                $totalPaid += $amount;
                
                if (!$lastPayment || ($payment['date'] ?? '') > $lastPaymentDate) {
                    $lastPayment = $payment;
                    $lastPaymentDate = $payment['date'] ?? null;
                }
            }
        }

        // Obtener información de membresía
        $membershipStatus = null;
        $membershipCreatedAt = null;
        if ($membership && isset($membership['memberships']) && !empty($membership['memberships'])) {
            // Obtener la membresía más reciente
            $latestMembership = null;
            foreach ($membership['memberships'] as $m) {
                if (!$latestMembership || 
                    (isset($m['createdAt']) && isset($latestMembership['createdAt']) && 
                     strtotime($m['createdAt']) > strtotime($latestMembership['createdAt']))) {
                    $latestMembership = $m;
                }
            }
            if ($latestMembership) {
                $membershipStatus = $latestMembership['status'] ?? null;
                $membershipCreatedAt = $latestMembership['createdAt'] ?? null;
            }
        }
        
        // Obtener campos personalizados de GHL
        $customFields = collect($contact['customFields'] ?? []);
        
        $ghlData = [
            // Campos de suscripciones y pagos
            'subscriptions' => $allSubscriptions ? json_encode($allSubscriptions) : null,
            'latest_subscription' => $latestSubscription ? json_encode($latestSubscription) : null,
            'payments' => $payments ? json_encode($payments) : null,
            'total_paid' => $totalPaid,
            'last_payment_date' => $lastPaymentDate,
            'last_payment_amount' => $lastPayment['amount'] ?? null,
            
            // Campos de membresía
            'membership_status' => $membershipStatus,
            'membership_created_at' => $membershipCreatedAt,
            'membership_data' => $membership ? json_encode($membership) : null,
            
            // Campos personalizados de GHL (extraídos del API)
            'relationship_status' => $customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? '-',
            'community_location' => $customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? '-',
            'country' => $contact['country'] ?? '-',
            'engagement_score' => $customFields->firstWhere('id', 'j175N7HO84AnJycpUb9D')['value'] ?? '-',
            'has_kids' => $customFields->firstWhere('id', 'xy0zfzMRFpOdXYJkHS2c')['value'] ?? '-',
            'state' => $contact['state'] ?? '-',
            'location' => $contact['city'] ?? '-',
            'zodiac_sign' => $customFields->firstWhere('id', 'JuiCbkHWsSc3iKfmOBpo')['value'] ?? '-',
            
            // Campos adicionales
            'contact_id' => $contact['id'],
            'tags' => json_encode($contact['tags'] ?? []),
            'date_added' => $contact['dateAdded'] ?? null,
            'GHL: Migrate GHL' => 'true'  // Campo específico con ID 844539743
        ];

        $this->info("   • Total pagado: $" . number_format($totalPaid, 2));
        $this->info("   • Pagos procesados: " . count($payments ?: []));
        $this->info("   • Suscripciones: " . ($allSubscriptions['total_subscriptions'] ?? 0));
        $this->info("   • Última suscripción: " . ($latestSubscription['status'] ?? 'No disponible'));
        $this->info("   • Estado de membresía: " . ($membershipStatus ?? 'No disponible'));
        $this->info("   • Fecha de membresía: " . ($membershipCreatedAt ?? 'No disponible'));
        $this->info("   • Tags: " . count($contact['tags'] ?? []));
        $this->info("   • Campos personalizados GHL: " . count($customFields));
        $this->info("   • Estado de relación: " . ($customFields->firstWhere('id', '1fFJJsONHbRMQJCstvg1')['value'] ?? 'N/A'));
        $this->info("   • Ubicación comunidad: " . ($customFields->firstWhere('id', 'q3BHfdxzT2uKfNO3icXG')['value'] ?? 'N/A'));
        $this->info("   • País: " . ($contact['country'] ?? 'N/A'));
        $this->info("   • Estado: " . ($contact['state'] ?? 'N/A'));
        $this->info("   • Ciudad: " . ($contact['city'] ?? 'N/A'));

        if ($dryRun) {
            $this->info("   🔍 DRY RUN: Se actualizarían los campos personalizados");
            return true;
        }

        try {
            $updateResult = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $ghlData);
            
            if ($updateResult) {
                $this->info("   ✅ Campos personalizados actualizados");
                return true;
            } else {
                $this->error("   ❌ Error actualizando campos personalizados");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error actualizando campos: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Detectar cupón desde GHL
     */
    private function detectCouponFromGHL(array $ghlData): ?string
    {
        $contact = $ghlData['contact'];

        // Buscar cupón en campos personalizados
        if (isset($contact['customFields']) && is_array($contact['customFields'])) {
            foreach ($contact['customFields'] as $field) {
                $fieldName = strtolower($field['name'] ?? '');
                $fieldValue = $field['value'] ?? '';
                
                // Buscar campos relacionados con cupones
                if (in_array($fieldName, ['coupon', 'coupon_code', 'discount_code', 'promo_code', 'codigo_descuento'])) {
                    if (!empty($fieldValue) && $fieldValue !== '-' && $fieldValue !== 'null') {
                        return $fieldValue;
                    }
                }
            }
        }

        // Buscar cupón en tags
        if (isset($contact['tags']) && is_array($contact['tags'])) {
            foreach ($contact['tags'] as $tag) {
                $tagLower = strtolower($tag);
                
                // Buscar patrones de cupones en tags
                if (preg_match('/^(wowfriday|creetelo|descuento|promo|cupon)/', $tagLower)) {
                    return $tag;
                }
            }
        }

        return null;
    }

    /**
     * Actualizar cupón en Baremetrics
     */
    private function updateCouponInBaremetrics(array $customer, string $couponCode, bool $dryRun): bool
    {
        if ($dryRun) {
            $this->info("   🔍 DRY RUN: Se actualizaría el cupón a: {$couponCode}");
            return true;
        }

        try {
            // Preparar datos para actualizar
            $couponData = [
                'coupon_code' => $couponCode,
                'coupon_applied' => 'true',
                'discount_source' => 'GHL Import'
            ];

            $updateResult = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $couponData);
            
            if ($updateResult) {
                $this->info("   ✅ Cupón actualizado: {$couponCode}");
                return true;
            } else {
                $this->error("   ❌ Error actualizando cupón");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error actualizando cupón: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extraer solo el nombre de la última suscripción
     */
    private function extractSubscriptionName(?array $latestSubscription): ?string
    {
        if (!$latestSubscription) {
            return null;
        }

        // Buscar el nombre en diferentes campos posibles
        $nameFields = [
            'productName',
            'name', 
            'product_name',
            'plan_name',
            'lineItemDetail.name',
            'recurringProd.name'
        ];

        foreach ($nameFields as $field) {
            if (strpos($field, '.') !== false) {
                // Campo anidado como lineItemDetail.name
                $parts = explode('.', $field);
                $value = $latestSubscription;
                foreach ($parts as $part) {
                    if (isset($value[$part])) {
                        $value = $value[$part];
                    } else {
                        $value = null;
                        break;
                    }
                }
                if ($value && !empty($value)) {
                    return $value;
                }
            } else {
                // Campo directo
                if (isset($latestSubscription[$field]) && !empty($latestSubscription[$field])) {
                    return $latestSubscription[$field];
                }
            }
        }

        // Si no encontramos nombre específico, usar un guión
        return '-';
    }

    /**
     * Actualizar campo GHL: Subscriptions en Baremetrics (NO en GHL)
     */
    private function updateGHLSubscriptionsFieldInBaremetrics(array $customer, ?array $latestSubscription, bool $dryRun): bool
    {
        // Extraer solo el nombre de la suscripción
        $subscriptionName = $this->extractSubscriptionName($latestSubscription);
        
        if (!$subscriptionName) {
            $this->warn("   ⚠️ No se pudo extraer el nombre de la suscripción");
            return false;
        }

        if ($dryRun) {
            $this->info("   🔍 DRY RUN: Se actualizaría campo 'GHL: Subscriptions' en Baremetrics");
            $this->info("   📋 Nombre de suscripción: " . $subscriptionName);
            return true;
        }

        try {
            // Usar solo el nombre de la suscripción, no todo el JSON
            $updateData = [
                'subscriptions' => $subscriptionName
            ];

            $this->info("   📡 Actualizando campo 'GHL: Subscriptions' en Baremetrics...");
            $result = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $updateData);
            
            if ($result) {
                $this->info("   ✅ Campo 'GHL: Subscriptions' actualizado en Baremetrics");
                $this->info("   📋 Nombre de suscripción: " . $subscriptionName);
                return true;
            } else {
                $this->error("   ❌ Error actualizando campo en Baremetrics");
                return false;
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error actualizando campo GHL: Subscriptions en Baremetrics: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Corregir fechas de suscripciones usando fecha real de GHL
     */
    private function fixSubscriptionDates(array $customer, array $ghlData, string $sourceId, bool $dryRun): bool
    {
        $contact = $ghlData['contact'];
        $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
        
        if (!$originalDate) {
            $this->warn("   ⚠️ No se encontró fecha original en GHL");
            return false;
        }

        $this->info("   📅 Fecha original encontrada: {$originalDate}");

        // Obtener suscripciones del cliente
        $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
        $customerSubscriptions = [];
        
        if ($subscriptions && isset($subscriptions['subscriptions'])) {
            foreach ($subscriptions['subscriptions'] as $subscription) {
                if ($subscription['customer']['oid'] === $customer['oid']) {
                    $customerSubscriptions[] = $subscription;
                }
            }
        }
        
        if (empty($customerSubscriptions)) {
            $this->warn("   ⚠️ No se encontraron suscripciones para el cliente");
            return false;
        }

        $fixed = false;
        foreach ($customerSubscriptions as $subscription) {
            $this->info("   🔄 Corrigiendo suscripción: {$subscription['oid']}");
            
            if ($dryRun) {
                $this->info("   🔍 DRY RUN: Se actualizaría started_at a " . strtotime($originalDate));
                $fixed = true;
                continue;
            }

            try {
                // Convertir fecha original a timestamp
                $startDate = new \DateTime($originalDate);
                $timestamp = $startDate->getTimestamp();

                // Actualizar suscripción con fecha correcta
                $updateData = ['started_at' => $timestamp];
                $result = $this->baremetricsService->updateSubscription($subscription['oid'], $updateData, $sourceId);
                
                if ($result) {
                    $this->info("   ✅ Suscripción actualizada con fecha: " . date('Y-m-d H:i:s', $timestamp));
                    $fixed = true;
                } else {
                    $this->error("   ❌ Error actualizando suscripción");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error procesando suscripción: " . $e->getMessage());
            }
        }

        return $fixed;
    }

    /**
     * Asignar suscripción al plan correcto usando fecha de membresía
     */
    private function assignSubscriptionToPlan(array $customer, array $ghlData, string $sourceId, bool $dryRun): bool
    {
        $contact = $ghlData['contact'];
        $membership = $ghlData['membership'];
        
        // Determinar el plan basado en tags
        $planData = $this->determinePlanFromTags($contact['tags'] ?? []);
        if (!$planData) {
            $this->warn("   ⚠️ No se pudo determinar el plan basado en tags");
            return false;
        }

        $this->info("   📋 Plan determinado: {$planData['name']} (OID: {$planData['oid']})");

        // Obtener fecha de inicio de la membresía
        $startDate = null;
        if ($membership && isset($membership['memberships']) && !empty($membership['memberships'])) {
            // Obtener la membresía más reciente
            $latestMembership = null;
            foreach ($membership['memberships'] as $m) {
                if (!$latestMembership || 
                    (isset($m['createdAt']) && isset($latestMembership['createdAt']) && 
                     strtotime($m['createdAt']) > strtotime($latestMembership['createdAt']))) {
                    $latestMembership = $m;
                }
            }
            if ($latestMembership && isset($latestMembership['createdAt'])) {
                $startDate = $latestMembership['createdAt'];
            }
        }

        // Si no hay fecha de membresía, usar fecha de contacto
        if (!$startDate) {
            $startDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
        }

        if (!$startDate) {
            $this->warn("   ⚠️ No se encontró fecha de inicio");
            return false;
        }

        $this->info("   📅 Fecha de inicio: {$startDate}");

        // Verificar si ya tiene suscripción activa
        $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
        $existingSubscription = null;
        
        if ($subscriptions && isset($subscriptions['subscriptions'])) {
            foreach ($subscriptions['subscriptions'] as $subscription) {
                $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                         $subscription['customer']['oid'] ?? 
                                         $subscription['customerOid'] ?? 
                                         null;
                
                if ($subscriptionCustomerOid === $customer['oid']) {
                    $existingSubscription = $subscription;
                    break;
                }
            }
        }

        if ($existingSubscription) {
            $this->info("   ✅ Usuario ya tiene suscripción activa: " . $existingSubscription['oid']);
            $this->info("   📋 Plan actual: " . ($existingSubscription['plan']['name'] ?? 'N/A'));
            
            // Verificar si el plan es correcto
            if (($existingSubscription['plan']['oid'] ?? '') === $planData['oid']) {
                $this->info("   ✅ El plan ya es correcto");
                return true;
            } else {
                $this->warn("   ⚠️ El plan actual no coincide con el esperado");
                $this->info("   💡 Se recomienda eliminar la suscripción actual y crear una nueva");
            }
        } else {
            // Crear nueva suscripción
            $this->info("   ➕ Creando nueva suscripción...");
            
            if ($dryRun) {
                $this->info("   🔍 DRY RUN: Se crearía suscripción con:");
                $this->info("      • Plan: {$planData['name']} (OID: {$planData['oid']})");
                $this->info("      • Fecha inicio: {$startDate}");
                return true;
            }

            try {
                // Convertir fecha a timestamp
                $startDateTime = new \DateTime($startDate);
                $timestamp = $startDateTime->getTimestamp();

                $subscriptionData = [
                    'customer_oid' => $customer['oid'],
                    'plan_oid' => $planData['oid'],
                    'started_at' => $timestamp,
                    'status' => 'active',
                    'oid' => 'sub_' . uniqid(),
                    'notes' => 'Suscripción asignada durante corrección de datos'
                ];

                $newSubscription = $this->baremetricsService->createSubscription($subscriptionData, $sourceId);
                
                if ($newSubscription) {
                    $this->info("   ✅ Suscripción creada exitosamente: " . ($newSubscription['oid'] ?? 'N/A'));
                    $this->info("   📅 Fecha de inicio: " . date('Y-m-d H:i:s', $timestamp));
                    return true;
                } else {
                    $this->error("   ❌ Error creando suscripción");
                    return false;
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error creando suscripción: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Determinar el plan basado en los tags del usuario
     */
    private function determinePlanFromTags(array $tags): ?array
    {
        if (empty($tags)) {
            return [
                'name' => 'creetelo_mensual',
                'oid' => '1759521305199'
            ];
        }

        foreach ($tags as $tag) {
            $tag = strtolower($tag);
            
            if (strpos($tag, 'creetelo_anual') !== false || strpos($tag, 'créetelo_anual') !== false) {
                return [
                    'name' => 'creetelo_anual',
                    'oid' => '1759827004232'
                ];
            }
            
            if (strpos($tag, 'creetelo_mensual') !== false || strpos($tag, 'créetelo_mensual') !== false) {
                return [
                    'name' => 'creetelo_mensual',
                    'oid' => '1759521305199'
                ];
            }
        }

        // Por defecto, usar plan mensual
        return [
            'name' => 'creetelo_mensual',
            'oid' => '1759521305199'
        ];
    }
}
