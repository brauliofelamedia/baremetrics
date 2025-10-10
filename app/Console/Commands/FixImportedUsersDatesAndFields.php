<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FixImportedUsersDatesAndFields extends Command
{
    protected $signature = 'baremetrics:fix-imported-users 
                           {--email= : Email específico del usuario a corregir}
                           {--all : Corregir todos los usuarios importados}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}
                           {--limit=50 : Límite de usuarios a procesar}';
    
    protected $description = 'Corrige las fechas de suscripciones y actualiza campos personalizados para usuarios ya importados en Baremetrics';

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

        $this->info("🔧 CORRECCIÓN DE USUARIOS IMPORTADOS");
        $this->info("====================================");
        $this->info("Email específico: " . ($email ?: 'No especificado'));
        $this->info("Procesar todos: " . ($all ? 'Sí' : 'No'));
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->info("Límite: {$limit} usuarios");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            if ($email) {
                $this->fixSpecificUser($email, $sourceId, $dryRun);
            } elseif ($all) {
                $this->fixAllImportedUsers($sourceId, $dryRun, $limit);
            } else {
                $this->error("❌ Debes especificar --email o --all");
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la corrección: " . $e->getMessage());
            Log::error('Error corrigiendo usuarios importados', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Corregir un usuario específico
     */
    private function fixSpecificUser(string $email, string $sourceId, bool $dryRun): void
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

        // 3. Corregir fechas de suscripciones
        $this->fixSubscriptionDates($customer, $ghlData, $sourceId, $dryRun);

        // 4. Actualizar campos personalizados
        $this->updateCustomFields($customer, $ghlData, $dryRun);
    }

    /**
     * Corregir todos los usuarios importados
     */
    private function fixAllImportedUsers(string $sourceId, bool $dryRun, int $limit): void
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

                // Corregir fechas de suscripciones
                $this->fixSubscriptionDates($customer, $ghlData, $sourceId, $dryRun);

                // Actualizar campos personalizados
                $this->updateCustomFields($customer, $ghlData, $dryRun);

                $processed++;

            } catch (\Exception $e) {
                $this->error("❌ Error procesando {$customer['email']}: " . $e->getMessage());
                Log::error('Error procesando usuario en corrección masiva', [
                    'email' => $customer['email'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Procesamiento completado. Usuarios procesados: {$processed}");
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
            
            // Obtener suscripciones
            $subscriptions = $this->ghlService->getSubscriptionStatusByContact($contact['id']);
            
            // Obtener pagos
            $payments = $this->ghlService->getContactPayments($contact['id']);
            
            return [
                'contact' => $contact,
                'subscriptions' => $subscriptions,
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
    private function fixSubscriptionDates(array $customer, array $ghlData, string $sourceId, bool $dryRun): void
    {
        $contact = $ghlData['contact'];
        $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
        
        if (!$originalDate) {
            $this->warn("⚠️ No se encontró fecha original en GHL");
            return;
        }

        $this->info("📅 Fecha original encontrada: {$originalDate}");

        // Obtener suscripciones del cliente
        $subscriptions = $this->baremetricsService->getSubscriptions($customer['oid'], $sourceId);
        
        if (!$subscriptions || !isset($subscriptions['subscriptions'])) {
            $this->warn("⚠️ No se encontraron suscripciones para el cliente");
            return;
        }

        foreach ($subscriptions['subscriptions'] as $subscription) {
            $this->info("🔄 Corrigiendo suscripción: {$subscription['oid']}");
            
            if ($dryRun) {
                $this->info("   🔍 DRY RUN: Se actualizaría started_at a " . strtotime($originalDate));
                continue;
            }

            try {
                // Convertir fecha original a timestamp
                $startDate = new \DateTime($originalDate);
                $timestamp = $startDate->getTimestamp();

                // Actualizar suscripción con fecha correcta
                $updateData = [
                    'started_at' => $timestamp
                ];

                $result = $this->baremetricsService->updateSubscription($subscription['oid'], $updateData, $sourceId);
                
                if ($result) {
                    $this->info("   ✅ Suscripción actualizada con fecha: " . date('Y-m-d H:i:s', $timestamp));
                } else {
                    $this->error("   ❌ Error actualizando suscripción");
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error procesando fecha: " . $e->getMessage());
            }
        }
    }

    /**
     * Actualizar campos personalizados
     */
    private function updateCustomFields(array $customer, array $ghlData, bool $dryRun): void
    {
        $contact = $ghlData['contact'];
        $subscriptions = $ghlData['subscriptions'];
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

        $ghlData = [
            'subscriptions' => $subscriptions ? json_encode($subscriptions) : null,
            'payments' => $payments ? json_encode($payments) : null,
            'total_paid' => $totalPaid,
            'last_payment_date' => $lastPaymentDate,
            'last_payment_amount' => $lastPayment['amount'] ?? null,
            'contact_id' => $contact['id'],
            'tags' => json_encode($contact['tags'] ?? []),
            'date_added' => $contact['dateAdded'] ?? null,
            'ghl_migrate' => 'true'
        ];

        $this->info("📋 Actualizando campos personalizados...");
        $this->info("   • Total pagado: $" . number_format($totalPaid, 2));
        $this->info("   • Pagos procesados: " . count($payments['payments'] ?? []));
        $this->info("   • Suscripciones: " . ($subscriptions['total_subscriptions'] ?? 0));
        $this->info("   • Tags: " . count($contact['tags'] ?? []));

        if ($dryRun) {
            $this->info("   🔍 DRY RUN: Se actualizarían los campos personalizados");
            return;
        }

        try {
            $updateResult = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $ghlData);
            
            if ($updateResult) {
                $this->info("   ✅ Campos personalizados actualizados exitosamente");
            } else {
                $this->error("   ❌ Error actualizando campos personalizados");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error actualizando campos: " . $e->getMessage());
        }
    }
}
