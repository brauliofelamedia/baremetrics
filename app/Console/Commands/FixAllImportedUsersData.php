<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FixAllImportedUsersData extends Command
{
    protected $signature = 'baremetrics:fix-all-imported-data 
                           {--email= : Email específico del usuario a corregir}
                           {--all : Corregir todos los usuarios importados}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}
                           {--limit=50 : Límite de usuarios a procesar}
                           {--skip-dates : Omitir corrección de fechas}
                           {--skip-fields : Omitir actualización de campos personalizados}
                           {--skip-coupons : Omitir actualización de cupones}';
    
    protected $description = 'Corrige completamente los datos de usuarios importados: fechas, campos personalizados y cupones';

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
        $email = $this->option('email');
        $all = $this->option('all');
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $skipDates = $this->option('skip-dates');
        $skipFields = $this->option('skip-fields');
        $skipCoupons = $this->option('skip-coupons');

        $this->info("🔧 CORRECCIÓN COMPLETA DE USUARIOS IMPORTADOS");
        $this->info("=============================================");
        $this->info("Email específico: " . ($email ?: 'No especificado'));
        $this->info("Procesar todos: " . ($all ? 'Sí' : 'No'));
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->info("Límite: {$limit} usuarios");
        $this->info("Omitir fechas: " . ($skipDates ? 'Sí' : 'No'));
        $this->info("Omitir campos: " . ($skipFields ? 'Sí' : 'No'));
        $this->info("Omitir cupones: " . ($skipCoupons ? 'Sí' : 'No'));
        $this->newLine();

        if (!$email && !$all) {
            $this->error("❌ Debes especificar --email o --all");
            return 1;
        }

        try {
            if ($email) {
                $this->fixSpecificUserComplete($email, $dryRun, $skipDates, $skipFields, $skipCoupons);
            } else {
                $this->fixAllUsersComplete($dryRun, $limit, $skipDates, $skipFields, $skipCoupons);
            }

            $this->newLine();
            $this->info("🎉 ¡Corrección completa finalizada!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la corrección completa: " . $e->getMessage());
            Log::error('Error en corrección completa de usuarios importados', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Corregir un usuario específico completamente
     */
    private function fixSpecificUserComplete(string $email, bool $dryRun, bool $skipDates, bool $skipFields, bool $skipCoupons): void
    {
        $this->info("🔍 Procesando usuario específico: {$email}");
        
        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        // 1. Buscar cliente en Baremetrics
        $customer = $this->findCustomerInBaremetrics($email, $sourceId);
        if (!$customer) {
            $this->error("❌ No se encontró el cliente en Baremetrics");
            return;
        }

        $this->info("✅ Cliente encontrado: {$customer['oid']}");

        // 2. Obtener datos reales desde GHL
        $ghlData = $this->getGHLData($email);
        if (!$ghlData) {
            $this->error("❌ No se encontraron datos en GHL");
            return;
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
    }

    /**
     * Corregir todos los usuarios completamente
     */
    private function fixAllUsersComplete(bool $dryRun, int $limit, bool $skipDates, bool $skipFields, bool $skipCoupons): void
    {
        $this->info("🔍 Obteniendo todos los clientes de Baremetrics...");
        
        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';
        
        $customers = $this->baremetricsService->getCustomers($sourceId);
        if (!$customers || !isset($customers['customers'])) {
            $this->error("❌ No se pudieron obtener los clientes de Baremetrics");
            return;
        }

        $customersList = $customers['customers'];
        $totalCustomers = count($customersList);
        $processed = 0;
        $datesFixed = 0;
        $fieldsUpdated = 0;
        $couponsUpdated = 0;

        $this->info("📊 Total de clientes encontrados: {$totalCustomers}");
        $this->info("🎯 Procesando máximo {$limit} clientes");
        $this->newLine();

        $progressBar = $this->output->createProgressBar(min($limit, $totalCustomers));
        $progressBar->start();

        foreach ($customersList as $customer) {
            if ($processed >= $limit) {
                break;
            }

            try {
                $email = $customer['email'] ?? null;
                if (!$email) {
                    $progressBar->advance();
                    continue;
                }

                // Verificar si tiene el campo "GHL: Migrate GHL" = true
                if (!$this->isImportedFromGHL($customer)) {
                    $progressBar->advance();
                    continue;
                }

                $this->newLine();
                $this->info("🔄 Procesando: {$email}");

                // Obtener datos de GHL
                $ghlData = $this->getGHLData($email);
                if (!$ghlData) {
                    $this->warn("⚠️ No se encontraron datos en GHL para: {$email}");
                    $progressBar->advance();
                    continue;
                }

                // Corregir fechas
                if (!$skipDates) {
                    if ($this->fixSubscriptionDates($customer, $ghlData, $sourceId, $dryRun)) {
                        $datesFixed++;
                    }
                }

                // Actualizar campos
                if (!$skipFields) {
                    if ($this->updateCustomFields($customer, $ghlData, $dryRun)) {
                        $fieldsUpdated++;
                    }
                }

                // Actualizar cupones
                if (!$skipCoupons) {
                    $couponCode = $this->detectCouponFromGHL($ghlData);
                    if ($couponCode && $this->updateCouponInBaremetrics($customer, $couponCode, $dryRun)) {
                        $couponsUpdated++;
                    }
                }

                // Asignar suscripción al plan correcto con fecha de membresía
                $this->assignSubscriptionToPlan($customer, $ghlData, $sourceId, $dryRun);

                // Actualizar campo GHL: Subscriptions en Baremetrics (NO en GHL)
                $contact = $ghlData['contact'];
                $latestSubscription = $ghlData['latest_subscription'];
                $this->updateGHLSubscriptionsFieldInBaremetrics($customer, $latestSubscription, $dryRun);

                $processed++;

            } catch (\Exception $e) {
                $this->error("❌ Error procesando {$customer['email']}: " . $e->getMessage());
                Log::error('Error procesando usuario en corrección completa', [
                    'email' => $customer['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Procesamiento completado.");
        $this->info("📊 Usuarios procesados: {$processed}");
        if (!$skipDates) $this->info("📅 Fechas corregidas: {$datesFixed}");
        if (!$skipFields) $this->info("📋 Campos actualizados: {$fieldsUpdated}");
        if (!$skipCoupons) $this->info("🎫 Cupones actualizados: {$couponsUpdated}");
    }

    /**
     * Buscar cliente en Baremetrics
     */
    private function findCustomerInBaremetrics(string $email, string $sourceId): ?array
    {
        $customers = $this->baremetricsService->getCustomers($sourceId);
        
        if (!$customers || !isset($customers['customers'])) {
            return null;
        }

        foreach ($customers['customers'] as $customer) {
            if (strtolower($customer['email']) === strtolower($email)) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Verificar si el cliente fue importado desde GHL
     */
    private function isImportedFromGHL(array $customer): bool
    {
        if (!isset($customer['attributes']) || !is_array($customer['attributes'])) {
            return false;
        }

        foreach ($customer['attributes'] as $attribute) {
            if (isset($attribute['name']) && $attribute['name'] === 'GHL: Migrate GHL') {
                return $attribute['value'] === 'true';
            }
        }

        return false;
    }

    /**
     * Obtener datos reales desde GHL
     */
    private function getGHLData(string $email): ?array
    {
        try {
            // Primero intentar búsqueda exacta
            $ghlResponse = $this->ghlService->getContactsByExactEmail($email);
            
            // Si no se encuentra con búsqueda exacta, intentar con contains
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $ghlResponse = $this->ghlService->getContacts($email);
            }
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                return null;
            }

            $contact = $ghlResponse['contacts'][0];
            
            // Obtener la última suscripción del usuario
            $latestSubscription = $this->ghlService->getMostRecentActiveSubscription($contact['id']);
            
            // Obtener todas las suscripciones para el campo GHL: Subscriptions
            $allSubscriptions = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
            
            // Obtener información de membresía
            $membership = $this->ghlService->getContactMembership($contact['id']);
            
            // Obtener pagos
            $payments = $this->ghlService->getContactPayments($contact['id']);
            
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
     * Corregir fechas de suscripciones
     */
    private function fixSubscriptionDates(array $customer, array $ghlData, string $sourceId, bool $dryRun): bool
    {
        $contact = $ghlData['contact'];
        $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
        
        if (!$originalDate) {
            $this->warn("⚠️ No se encontró fecha original en GHL");
            return false;
        }

        // Obtener suscripciones del cliente
        $subscriptions = $this->baremetricsService->getSubscriptions($customer['oid'], $sourceId);
        
        if (!$subscriptions || !isset($subscriptions['subscriptions'])) {
            $this->warn("⚠️ No se encontraron suscripciones para el cliente");
            return false;
        }

        $fixed = false;
        foreach ($subscriptions['subscriptions'] as $subscription) {
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
                $this->error("   ❌ Error procesando fecha: " . $e->getMessage());
            }
        }

        return $fixed;
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

        if ($payments && isset($payments['payments'])) {
            foreach ($payments['payments'] as $payment) {
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
            return false;
        }

        if ($dryRun) {
            return true;
        }

        try {
            // Usar solo el nombre de la suscripción, no todo el JSON
            $updateData = [
                'subscriptions' => $subscriptionName
            ];

            $result = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $updateData);
            return $result;

        } catch (\Exception $e) {
            Log::error('Error actualizando campo GHL: Subscriptions en Baremetrics', [
                'customer_oid' => $customer['oid'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
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
            return false;
        }

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
            return false;
        }

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
            // Verificar si el plan es correcto
            if (($existingSubscription['plan']['oid'] ?? '') === $planData['oid']) {
                return true;
            }
        } else {
            // Crear nueva suscripción
            if ($dryRun) {
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
                return $newSubscription !== null;

            } catch (\Exception $e) {
                Log::error('Error creando suscripción en corrección masiva', [
                    'customer_oid' => $customer['oid'],
                    'error' => $e->getMessage()
                ]);
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
