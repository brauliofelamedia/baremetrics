<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class DeleteGHLMigrateUsers extends Command
{
    protected $signature = 'baremetrics:delete-ghl-migrate-users 
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}
                           {--confirm : Confirmar eliminación sin preguntar}
                           {--dry-run : Solo mostrar qué se eliminaría sin hacer cambios}
                           {--batch-size=10 : Número de usuarios a procesar por lote}';
    
    protected $description = 'Elimina usuarios que tienen el campo personalizado "GHL: migrate = true" y sus suscripciones';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $sourceId = $this->option('source-id');
        $confirm = $this->option('confirm');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $this->info("🗑️ ELIMINACIÓN DE USUARIOS CON 'GHL: Migrate GHL = true'");
        $this->info("=====================================================");
        $this->info("Source ID: {$sourceId}");
        $this->info("Modo: " . ($dryRun ? "DRY RUN (solo simulación)" : "ELIMINACIÓN REAL"));
        $this->info("Tamaño de lote: {$batchSize}");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            $this->info("🔍 Obteniendo usuarios con 'GHL: Migrate GHL = true'...");
            
            // Obtener usuarios con paginación completa
            $this->info("🔍 Obteniendo usuarios de Baremetrics (con paginación)...");
            
            $allCustomers = [];
            $page = 0;
            $hasMore = true;
            
            while ($hasMore) {
                $response = $this->baremetricsService->getCustomers($sourceId, '', $page);
                
                if (!$response) {
                    $this->error("❌ No se pudo obtener respuesta de la página {$page}");
                    break;
                }
                
                $customers = [];
                if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
                    $customers = $response['customers'];
                } elseif (is_array($response)) {
                    $customers = $response;
                }
                
                $allCustomers = array_merge($allCustomers, $customers);
                
                // Verificar paginación
                if (isset($response['meta']['pagination'])) {
                    $pagination = $response['meta']['pagination'];
                    $hasMore = $pagination['has_more'] ?? false;
                } else {
                    $hasMore = false;
                }
                
                $this->info("   Página {$page}: " . count($customers) . " usuarios obtenidos");
                $page++;
                
                if ($page > 10) { // Límite de seguridad
                    $this->warn("⚠️ Límite de páginas alcanzado (10)");
                    break;
                }
                
                usleep(100000); // Pausa entre requests
            }

            // Filtrar usuarios con el campo "GHL: Migrate GHL = true"
            $migrateUsers = [];
            foreach ($allCustomers as $customer) {
                $properties = $customer['properties'] ?? [];
                
                // Buscar el campo por ID: 844539743 (GHL: Migrate GHL)
                foreach ($properties as $field) {
                    if (isset($field['field_id']) && $field['field_id'] === '844539743') {
                        if (isset($field['value']) && $field['value'] === 'true') {
                            $migrateUsers[] = $customer;
                            break;
                        }
                    }
                }
            }

            $totalMigrateUsers = count($migrateUsers);
            
            if ($totalMigrateUsers === 0) {
                $this->warn("⚠️ No se encontraron usuarios con el campo 'GHL: Migrate GHL = true'");
                return 0;
            }

            $this->info("✅ Usuarios con 'GHL: Migrate GHL = true' encontrados: {$totalMigrateUsers}");
            $this->newLine();

            // Obtener suscripciones para calcular el total
            $this->info("🔍 Calculando suscripciones totales...");
            $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
            $totalSubscriptions = 0;
            $userSubscriptions = [];

            if ($subscriptions && isset($subscriptions['subscriptions'])) {
                foreach ($migrateUsers as $user) {
                    $customerId = $user['oid'];
                    $userSubscriptions[$customerId] = [];
                    
                    foreach ($subscriptions['subscriptions'] as $subscription) {
                        $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                 $subscription['customer']['oid'] ?? 
                                                 $subscription['customerOid'] ?? 
                                                 null;
                        
                        if ($subscriptionCustomerOid === $customerId) {
                            $userSubscriptions[$customerId][] = $subscription;
                            $totalSubscriptions++;
                        }
                    }
                }
            }

            // Mostrar resumen de lo que se va a eliminar
            $this->info("📊 RESUMEN DE ELIMINACIÓN:");
            $this->info("==========================");
            $this->info("• Usuarios a eliminar: {$totalMigrateUsers}");
            $this->info("• Suscripciones a eliminar: {$totalSubscriptions}");
            $this->info("• Tamaño de lote: {$batchSize}");
            $this->newLine();

            // Mostrar lista de usuarios
            $this->info("📋 USUARIOS A ELIMINAR:");
            foreach ($migrateUsers as $index => $user) {
                $customerId = $user['oid'];
                $email = $user['email'] ?? 'N/A';
                $name = $user['name'] ?? 'N/A';
                $subscriptionCount = count($userSubscriptions[$customerId] ?? []);
                
                $this->info("   " . ($index + 1) . ". {$email} ({$name}) - {$subscriptionCount} suscripciones");
            }
            $this->newLine();

            // Confirmar eliminación
            if (!$dryRun && !$confirm) {
                $this->warn("⚠️  ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE:");
                $this->warn("   • {$totalMigrateUsers} usuarios");
                $this->warn("   • {$totalSubscriptions} suscripciones");
                $this->warn("   • TODOS los datos asociados");
                $this->warn("   • NO se re-importarán los usuarios");
                $this->newLine();

                if (!$this->confirm('¿Estás seguro de que quieres continuar?')) {
                    $this->info("❌ Operación cancelada por el usuario");
                    return 0;
                }
            }

            if ($dryRun) {
                $this->info("🔍 MODO DRY RUN - No se realizarán cambios reales");
                $this->newLine();
            }

            // Procesar en lotes
            $processedUsers = 0;
            $deletedUsers = 0;
            $deletedSubscriptions = 0;
            $failedUsers = 0;
            $failedSubscriptions = 0;

            $batches = array_chunk($migrateUsers, $batchSize);
            
            foreach ($batches as $batchIndex => $batch) {
                $this->info("📦 Procesando lote " . ($batchIndex + 1) . " de " . count($batches) . " (" . count($batch) . " usuarios)");
                
                foreach ($batch as $user) {
                    $customerId = $user['oid'];
                    $email = $user['email'] ?? 'N/A';
                    $name = $user['name'] ?? 'N/A';
                    $userSubs = $userSubscriptions[$customerId] ?? [];
                    
                    $this->info("   👤 Procesando: {$email} ({$name})");
                    $this->info("      Customer ID: {$customerId}");
                    $this->info("      Suscripciones: " . count($userSubs));
                    
                    if ($dryRun) {
                        $this->info("      🔍 DRY RUN: Se eliminarían " . count($userSubs) . " suscripciones y 1 usuario");
                        $deletedUsers++;
                        $deletedSubscriptions += count($userSubs);
                    } else {
                        // Eliminar suscripciones del usuario
                        $userDeletedSubs = 0;
                        $userFailedSubs = 0;
                        
                        foreach ($userSubs as $subscription) {
                            $subscriptionOid = $subscription['oid'];
                            $this->info("         🗑️ Eliminando suscripción: {$subscriptionOid}");
                            
                            $deleteResult = $this->baremetricsService->deleteSubscription($sourceId, $subscriptionOid);
                            if ($deleteResult) {
                                $this->info("         ✅ Suscripción eliminada");
                                $userDeletedSubs++;
                            } else {
                                $this->error("         ❌ Error eliminando suscripción");
                                $userFailedSubs++;
                            }
                        }
                        
                        // Eliminar usuario
                        $this->info("      🗑️ Eliminando usuario: {$customerId}");
                        $deleteCustomerResult = $this->baremetricsService->deleteCustomer($sourceId, $customerId);
                        
                        if ($deleteCustomerResult) {
                            $this->info("      ✅ Usuario eliminado");
                            $deletedUsers++;
                            $deletedSubscriptions += $userDeletedSubs;
                        } else {
                            $this->error("      ❌ Error eliminando usuario");
                            $failedUsers++;
                        }
                        
                        $failedSubscriptions += $userFailedSubs;
                    }
                    
                    $processedUsers++;
                    $this->newLine();
                }
                
                // Pausa entre lotes para evitar sobrecargar la API
                if (!$dryRun && $batchIndex < count($batches) - 1) {
                    $this->info("⏳ Pausa de 2 segundos entre lotes...");
                    sleep(2);
                }
            }

            // Resumen final
            $this->newLine();
            $this->info("🎉 PROCESO COMPLETADO");
            $this->info("=====================");
            $this->info("✅ Usuarios procesados: {$processedUsers}");
            $this->info("✅ Usuarios eliminados: {$deletedUsers}");
            $this->info("✅ Suscripciones eliminadas: {$deletedSubscriptions}");
            
            if ($failedUsers > 0 || $failedSubscriptions > 0) {
                $this->warn("⚠️ Fallos:");
                $this->warn("   • Usuarios fallidos: {$failedUsers}");
                $this->warn("   • Suscripciones fallidas: {$failedSubscriptions}");
            }

            if ($dryRun) {
                $this->info("🔍 MODO DRY RUN - No se realizaron cambios reales");
                $this->info("💡 Para ejecutar la eliminación real, ejecuta el comando sin --dry-run");
            } else {
                $this->info("💡 Para re-importar usuarios, usa los comandos de importación correspondientes");
            }

            // Log de la operación
            Log::info('Usuarios con GHL migrate flag eliminados', [
                'source_id' => $sourceId,
                'total_users' => $totalMigrateUsers,
                'total_subscriptions' => $totalSubscriptions,
                'processed_users' => $processedUsers,
                'deleted_users' => $deletedUsers,
                'deleted_subscriptions' => $deletedSubscriptions,
                'failed_users' => $failedUsers,
                'failed_subscriptions' => $failedSubscriptions,
                'dry_run' => $dryRun,
                'batch_size' => $batchSize
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Error durante la eliminación: " . $e->getMessage());
            Log::error('Error eliminando usuarios con GHL migrate flag', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
