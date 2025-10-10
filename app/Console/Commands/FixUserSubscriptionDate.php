<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class FixUserSubscriptionDate extends Command
{
    protected $signature = 'baremetrics:fix-subscription-date 
                           {customer_id : ID del cliente en Baremetrics (ej: cust_68e55c311a2b9)}
                           {--dry-run : Solo mostrar qué se haría sin hacer cambios}';
    
    protected $description = 'Corrige la fecha de suscripción eliminando la actual y creando una nueva con la fecha correcta';

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
        $customerId = $this->argument('customer_id');
        $dryRun = $this->option('dry-run');

        $this->info("🔧 CORRECCIÓN DE FECHA DE SUSCRIPCIÓN");
        $this->info("====================================");
        $this->info("ID del cliente: {$customerId}");
        $this->info("Modo dry-run: " . ($dryRun ? 'Sí' : 'No'));
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();
        
        // Source ID de Baremetrics manual
        $sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

        try {
            // 1. Buscar cliente por ID en Baremetrics
            $customer = $this->findCustomerById($customerId, $sourceId);
            if (!$customer) {
                $this->error("❌ No se encontró el cliente con ID: {$customerId}");
                return 1;
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
            $originalDate = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
            
            if (!$originalDate) {
                $this->error("❌ No se encontró fecha original en GHL");
                return 1;
            }

            $this->info("👤 Usuario GHL: {$contact['firstName']} {$contact['lastName']}");
            $this->info("📅 Fecha original: {$originalDate}");

            // 3. Obtener suscripciones actuales del cliente
            $customerSubscriptions = $this->getCustomerSubscriptions($customer, $sourceId);
            
            if (empty($customerSubscriptions)) {
                $this->warn("⚠️ No se encontraron suscripciones para el cliente");
                return 1;
            }

            $this->info("📋 Suscripciones encontradas: " . count($customerSubscriptions));

            // 4. Procesar cada suscripción
            foreach ($customerSubscriptions as $subscription) {
                $this->info("🔄 Procesando suscripción: {$subscription['oid']}");
                
                // Obtener información del plan
                $planOid = $subscription['plan_oid'] ?? $subscription['plan']['oid'] ?? null;
                if (!$planOid) {
                    $this->error("   ❌ No se pudo obtener el plan de la suscripción");
                    continue;
                }

                $this->info("   📋 Plan: {$planOid}");
                $this->info("   📅 Fecha actual: " . date('Y-m-d H:i:s', $subscription['started_at'] ?? 0));

                if ($dryRun) {
                    $this->info("   🔍 DRY RUN: Se eliminaría la suscripción actual y se crearía una nueva con fecha: " . date('Y-m-d H:i:s', strtotime($originalDate)));
                    continue;
                }

                try {
                    // Eliminar suscripción actual
                    $this->info("   🗑️ Eliminando suscripción actual...");
                    $deleteResult = $this->baremetricsService->deleteSubscription($sourceId, $subscription['oid']);
                    
                    if (!$deleteResult) {
                        $this->error("   ❌ Error eliminando suscripción");
                        continue;
                    }
                    
                    $this->info("   ✅ Suscripción eliminada");

                    // Crear nueva suscripción con fecha correcta
                    $this->info("   ➕ Creando nueva suscripción con fecha correcta...");
                    
                    $startDate = new \DateTime($originalDate);
                    $timestamp = $startDate->getTimestamp();
                    
                    $newSubscriptionData = [
                        'customer_oid' => $customer['oid'],
                        'plan_oid' => $planOid,
                        'started_at' => $timestamp,
                        'status' => 'active',
                        'oid' => 'sub_' . uniqid(),
                        'notes' => 'Recreada con fecha original de GHL'
                    ];

                    // También actualizar el campo GHL: Migrate GHL
                    $this->info("   📋 Actualizando campo GHL: Migrate GHL...");
                    $migrateData = ['GHL: Migrate GHL' => 'true'];
                    $migrateResult = $this->baremetricsService->updateCustomerAttributes($customer['oid'], $migrateData);
                    
                    if ($migrateResult) {
                        $this->info("   ✅ Campo GHL: Migrate GHL actualizado");
                    } else {
                        $this->warn("   ⚠️ Error actualizando campo GHL: Migrate GHL");
                    }

                    $createResult = $this->baremetricsService->createSubscription($newSubscriptionData, $sourceId);
                    
                    if ($createResult) {
                        $this->info("   ✅ Nueva suscripción creada con fecha: " . date('Y-m-d H:i:s', $timestamp));
                    } else {
                        $this->error("   ❌ Error creando nueva suscripción");
                    }

                } catch (\Exception $e) {
                    $this->error("   ❌ Error procesando suscripción: " . $e->getMessage());
                }
            }

            $this->newLine();
            $this->info("🎉 ¡Corrección de fecha completada para el usuario {$customerId}!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la corrección: " . $e->getMessage());
            Log::error('Error corrigiendo fecha de suscripción', [
                'customer_id' => $customerId,
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
     * Obtener suscripciones del cliente
     */
    private function getCustomerSubscriptions(array $customer, string $sourceId): array
    {
        try {
            // Obtener todas las suscripciones del source
            $allSubscriptions = $this->baremetricsService->getSubscriptions($sourceId);
            
            if (!$allSubscriptions || !isset($allSubscriptions['subscriptions'])) {
                return [];
            }

            // Filtrar suscripciones del cliente específico
            $customerSubscriptions = [];
            foreach ($allSubscriptions['subscriptions'] as $subscription) {
                $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                         $subscription['customer']['oid'] ?? 
                                         $subscription['customerOid'] ?? 
                                         null;
                
                if ($subscriptionCustomerOid === $customer['oid']) {
                    $customerSubscriptions[] = $subscription;
                }
            }

            return $customerSubscriptions;

        } catch (\Exception $e) {
            Log::error('Error obteniendo suscripciones del cliente', [
                'customer_id' => $customer['oid'],
                'error' => $e->getMessage()
            ]);
            return [];
        }
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
            Log::error('Error obteniendo datos de GHL', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
