<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class UpdateCouponsForImportedUsers extends Command
{
    protected $signature = 'baremetrics:update-coupons 
                           {--email= : Email específico del usuario a actualizar}
                           {--all : Actualizar todos los usuarios importados}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}
                           {--limit=50 : Límite de usuarios a procesar}
                           {--coupon= : Cupón específico a aplicar}';
    
    protected $description = 'Actualiza información de cupones para usuarios ya importados en Baremetrics';

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
        $specificCoupon = $this->option('coupon');

        $this->info("🎫 ACTUALIZACIÓN DE CUPONES PARA USUARIOS IMPORTADOS");
        $this->info("==================================================");
        $this->info("Email específico: " . ($email ?: 'No especificado'));
        $this->info("Procesar todos: " . ($all ? 'Sí' : 'No'));
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->info("Límite: {$limit} usuarios");
        $this->info("Cupón específico: " . ($specificCoupon ?: 'Detectar desde GHL'));
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            if ($email) {
                $this->updateSpecificUserCoupon($email, $sourceId, $dryRun, $specificCoupon);
            } elseif ($all) {
                $this->updateAllUsersCoupons($sourceId, $dryRun, $limit, $specificCoupon);
            } else {
                $this->error("❌ Debes especificar --email o --all");
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la actualización de cupones: " . $e->getMessage());
            Log::error('Error actualizando cupones de usuarios importados', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Actualizar cupón de un usuario específico
     */
    private function updateSpecificUserCoupon(string $email, string $sourceId, bool $dryRun, ?string $specificCoupon): void
    {
        $this->info("🔍 Procesando usuario específico: {$email}");
        
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

        // 3. Detectar cupón
        $couponCode = $this->detectCouponFromGHL($ghlData, $specificCoupon);
        
        if (!$couponCode) {
            $this->warn("⚠️ No se encontró cupón para este usuario");
            return;
        }

        $this->info("🎫 Cupón detectado: {$couponCode}");

        // 4. Actualizar cupón en Baremetrics
        $this->updateCouponInBaremetrics($customer, $couponCode, $dryRun);
    }

    /**
     * Actualizar cupones de todos los usuarios importados
     */
    private function updateAllUsersCoupons(string $sourceId, bool $dryRun, int $limit, ?string $specificCoupon): void
    {
        $this->info("🔍 Obteniendo todos los clientes de Baremetrics...");
        
        $customers = $this->baremetricsService->getCustomers($sourceId);
        if (!$customers || !isset($customers['customers'])) {
            $this->error("❌ No se pudieron obtener los clientes de Baremetrics");
            return;
        }

        $customersList = $customers['customers'];
        $totalCustomers = count($customersList);
        $processed = 0;
        $updated = 0;

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

                // Detectar cupón
                $couponCode = $this->detectCouponFromGHL($ghlData, $specificCoupon);
                
                if ($couponCode) {
                    $this->info("🎫 Cupón detectado: {$couponCode}");
                    
                    // Actualizar cupón en Baremetrics
                    if ($this->updateCouponInBaremetrics($customer, $couponCode, $dryRun)) {
                        $updated++;
                    }
                } else {
                    $this->warn("⚠️ No se encontró cupón para: {$email}");
                }

                $processed++;

            } catch (\Exception $e) {
                $this->error("❌ Error procesando {$customer['email']}: " . $e->getMessage());
                Log::error('Error procesando cupón de usuario', [
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
        $this->info("🎫 Cupones actualizados: {$updated}");
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
            $ghlResponse = $this->ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                return null;
            }

            $contact = $ghlResponse['contacts'][0];
            
            return [
                'contact' => $contact
            ];

        } catch (\Exception $e) {
            Log::error('Error obteniendo datos de GHL para cupones', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detectar cupón desde GHL
     */
    private function detectCouponFromGHL(array $ghlData, ?string $specificCoupon): ?string
    {
        // Si se especifica un cupón específico, usarlo
        if ($specificCoupon) {
            return $specificCoupon;
        }

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
        $this->info("🔄 Actualizando cupón en Baremetrics...");

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
                $this->info("   ✅ Cupón actualizado exitosamente: {$couponCode}");
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
}
