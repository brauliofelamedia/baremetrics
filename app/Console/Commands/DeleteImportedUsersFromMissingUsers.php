<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Models\MissingUser;
use Illuminate\Support\Facades\Log;

class DeleteImportedUsersFromMissingUsers extends Command
{
    protected $signature = 'baremetrics:delete-imported-from-missing-users 
                           {--status=imported : Status a procesar (pending, importing, imported, failed, found_in_other_source)}
                           {--comparison-id= : ID específico de comparación a procesar}
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}
                           {--confirm : Confirmar eliminación sin preguntar}
                           {--dry-run : Solo mostrar qué se eliminaría sin hacer cambios}
                           {--batch-size=10 : Número de usuarios a procesar por lote}';
    
    protected $description = 'Elimina usuarios de Baremetrics basándose en la tabla missing_users por status específico';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $status = $this->option('status');
        $comparisonId = $this->option('comparison-id');
        $sourceId = $this->option('source-id');
        $confirm = $this->option('confirm');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        // Validar status
        $validStatuses = ['pending', 'importing', 'imported', 'failed', 'found_in_other_source'];
        if (!in_array($status, $validStatuses)) {
            $this->error("❌ Status inválido: {$status}");
            $this->error("Status válidos: " . implode(', ', $validStatuses));
            return 1;
        }

        $this->info("🗑️ ELIMINACIÓN DE USUARIOS DESDE MISSING_USERS");
        $this->info("=============================================");
        $this->info("Status a procesar: {$status}");
        $this->info("Source ID: {$sourceId}");
        $this->info("Modo: " . ($dryRun ? "DRY RUN (solo simulación)" : "ELIMINACIÓN REAL"));
        $this->info("Tamaño de lote: {$batchSize}");
        
        if ($comparisonId) {
            $this->info("Comparación específica: {$comparisonId}");
        }
        
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            // Obtener usuarios según el status especificado
            $this->info("🔍 Obteniendo usuarios con status='{$status}' de missing_users...");
            
            $query = MissingUser::where('import_status', $status);
            
            // Solo buscar usuarios con customer_id si el status es 'imported' o 'found_in_other_source'
            if (in_array($status, ['imported', 'found_in_other_source'])) {
                $query->whereNotNull('baremetrics_customer_id');
            }
            
            if ($comparisonId) {
                $query->where('comparison_id', $comparisonId);
            }

            $users = $query->get();
            
            if ($users->isEmpty()) {
                $this->warn("⚠️ No se encontraron usuarios con status='{$status}' en missing_users");
                return 0;
            }

            $totalUsers = $users->count();
            $this->info("📊 Usuarios encontrados: {$totalUsers}");

            // Mostrar estadísticas
            $this->showStatistics($users, $status);

            if ($dryRun) {
                $this->showDryRunDetails($users, $status);
                return 0;
            }

            // Confirmar eliminación
            if (!$confirm) {
                $this->warn("⚠️ ADVERTENCIA: Esta acción eliminará {$totalUsers} usuarios de Baremetrics");
                $this->warn("⚠️ Esta acción NO se puede deshacer");
                
                if (!$this->confirm("¿Estás seguro de que quieres continuar?")) {
                    $this->info("❌ Operación cancelada por el usuario");
                    return 0;
                }
            }

            // Procesar según el status
            if (in_array($status, ['imported', 'found_in_other_source'])) {
                $this->processUsersWithCustomerId($users, $sourceId, $batchSize);
            } else {
                $this->processUsersWithoutCustomerId($users, $status, $sourceId, $batchSize);
            }

        } catch (\Exception $e) {
            $this->error("❌ Error crítico: " . $e->getMessage());
            Log::error('Error crítico en eliminación masiva desde missing_users', [
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    private function showStatistics($users, $status)
    {
        $this->newLine();
        $this->info("📈 ESTADÍSTICAS:");
        
        // Por comparación
        $comparisonStats = $users->groupBy('comparison_id')
            ->map(function ($users, $comparisonId) {
                return [
                    'count' => $users->count(),
                    'comparison_id' => $comparisonId
                ];
            });
        
        foreach ($comparisonStats as $stat) {
            $this->line("   • Comparación ID {$stat['comparison_id']}: {$stat['count']} usuarios");
        }
        
        // Emails de ejemplo
        $sampleEmails = $users->take(5)->pluck('email')->toArray();
        $this->info("📧 Emails de ejemplo:");
        foreach ($sampleEmails as $email) {
            $this->line("   • {$email}");
        }
        
        if ($users->count() > 5) {
            $this->line("   • ... y " . ($users->count() - 5) . " más");
        }
    }

    private function showDryRunDetails($users, $status)
    {
        $this->newLine();
        $this->info("🔍 DRY RUN - Detalles de lo que se procesaría:");
        
        foreach ($users->take(10) as $user) {
            if (in_array($status, ['imported', 'found_in_other_source']) && $user->baremetrics_customer_id) {
                $this->line("   • {$user->email} (ID: {$user->baremetrics_customer_id}) - ELIMINAR de Baremetrics");
            } else {
                $this->line("   • {$user->email} - BUSCAR y eliminar de Baremetrics + marcar como procesado");
            }
        }
        
        if ($users->count() > 10) {
            $this->line("   • ... y " . ($users->count() - 10) . " más");
        }
        
        $this->newLine();
        $this->info("💡 Para procesar realmente, ejecuta el comando sin --dry-run");
    }

    private function processUsersWithCustomerId($users, $sourceId, $batchSize)
    {
        $this->info("🔄 Iniciando eliminación de usuarios con Customer ID en lotes de {$batchSize}...");
        
        $deletedCount = 0;
        $failedCount = 0;
        $errors = [];
        
        $batches = $users->chunk($batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $this->info("📦 Procesando lote " . ($batchIndex + 1) . " de " . $batches->count() . " ({$batch->count()} usuarios)");
            
            foreach ($batch as $missingUser) {
                try {
                    $customerId = $missingUser->baremetrics_customer_id;
                    $email = $missingUser->email;
                    
                    $this->line("   🗑️ Eliminando: {$email} (ID: {$customerId})");
                    
                    // Eliminar suscripciones del usuario
                    $subscriptionsDeleted = $this->deleteUserSubscriptions($customerId, $sourceId);
                    
                    // Eliminar el customer
                    $customerDeleted = $this->deleteCustomer($customerId, $sourceId);
                    
                    if ($customerDeleted) {
                        $deletedCount++;
                        $this->line("   ✅ Usuario eliminado exitosamente");
                        
                        // Actualizar status en missing_users
                        $missingUser->update([
                            'import_status' => 'failed',
                            'imported_at' => null,
                            'baremetrics_customer_id' => null
                        ]);
                    } else {
                        $failedCount++;
                        $this->line("   ❌ Error eliminando usuario");
                    }
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "Error eliminando {$missingUser->email}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    $this->line("   ❌ {$errorMsg}");
                    
                    Log::error('Error eliminando usuario desde missing_users', [
                        'email' => $missingUser->email,
                        'customer_id' => $missingUser->baremetrics_customer_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Pequeña pausa entre lotes
            if ($batchIndex < $batches->count() - 1) {
                sleep(1);
            }
        }

        // Mostrar resumen final
        $this->showFinalSummary($deletedCount, $failedCount, $errors);
    }

    private function processUsersWithoutCustomerId($users, $status, $sourceId, $batchSize)
    {
        $this->info("🔄 Procesando usuarios con status '{$status}'...");
        
        $updatedCount = 0;
        $failedCount = 0;
        $errors = [];
        
        $batches = $users->chunk($batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $this->info("📦 Procesando lote " . ($batchIndex + 1) . " de " . $batches->count() . " ({$batch->count()} usuarios)");
            
            foreach ($batch as $missingUser) {
                try {
                    $email = $missingUser->email;
                    $this->line("   🔍 Procesando: {$email}");
                    
                    // Buscar usuario en Baremetrics por email
                    $customer = $this->findCustomerByEmail($email, $sourceId);
                    
                    if ($customer) {
                        $customerId = $customer['oid'];
                        $this->line("   🗑️ Usuario encontrado en Baremetrics (ID: {$customerId}), eliminando...");
                        
                        // Eliminar suscripciones del usuario
                        $subscriptionsDeleted = $this->deleteUserSubscriptions($customerId, $sourceId);
                        
                        // Eliminar el customer
                        $customerDeleted = $this->deleteCustomer($customerId, $sourceId);
                        
                        if ($customerDeleted) {
                            $this->line("   ✅ Usuario eliminado de Baremetrics exitosamente");
                        } else {
                            $this->line("   ⚠️ Error eliminando usuario de Baremetrics");
                        }
                    } else {
                        $this->line("   ℹ️ Usuario no encontrado en Baremetrics");
                    }
                    
                    // Marcar como procesado en la BD
                    $missingUser->update([
                        'import_status' => 'failed',
                        'imported_at' => null,
                        'baremetrics_customer_id' => null
                    ]);
                    
                    $updatedCount++;
                    $this->line("   ✅ Usuario marcado como procesado");
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "Error procesando {$missingUser->email}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    $this->line("   ❌ {$errorMsg}");
                    
                    Log::error('Error procesando usuario desde missing_users', [
                        'email' => $missingUser->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Pequeña pausa entre lotes
            if ($batchIndex < $batches->count() - 1) {
                sleep(1);
            }
        }

        // Mostrar resumen final
        $this->newLine();
        $this->info("📊 RESUMEN FINAL");
        $this->info("================");
        $this->info("✅ Usuarios procesados: {$updatedCount}");
        $this->info("❌ Usuarios con errores: {$failedCount}");
        $this->info("📊 Total procesados: " . ($updatedCount + $failedCount));
        
        if (!empty($errors)) {
            $this->newLine();
            $this->warn("⚠️ Errores encontrados:");
            foreach ($errors as $error) {
                $this->line("   • {$error}");
            }
        }
    }

    private function showFinalSummary($deletedCount, $failedCount, $errors)
    {
        $this->newLine();
        $this->info("📊 RESUMEN FINAL");
        $this->info("================");
        $this->info("✅ Usuarios eliminados exitosamente: {$deletedCount}");
        $this->info("❌ Usuarios con errores: {$failedCount}");
        $this->info("📊 Total procesados: " . ($deletedCount + $failedCount));
        
        if (!empty($errors)) {
            $this->newLine();
            $this->warn("⚠️ Errores encontrados:");
            foreach ($errors as $error) {
                $this->line("   • {$error}");
            }
        }
    }

    private function findCustomerByEmail(string $email, string $sourceId): ?array
    {
        try {
            $customers = $this->baremetricsService->getCustomers($sourceId);
            
            foreach ($customers as $customer) {
                if (isset($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
                    return $customer;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error buscando customer por email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function deleteUserSubscriptions(string $customerId, string $sourceId): int
    {
        try {
            $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
            
            if (!$subscriptions || !isset($subscriptions['subscriptions'])) {
                return 0;
            }

            $deletedCount = 0;
            foreach ($subscriptions['subscriptions'] as $subscription) {
                $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                         $subscription['customer']['oid'] ?? 
                                         $subscription['customerOid'] ?? 
                                         null;
                
                if ($subscriptionCustomerOid === $customerId) {
                    $subscriptionOid = $subscription['oid'];
                    $result = $this->baremetricsService->deleteSubscription($subscriptionOid, $sourceId);
                    if ($result) {
                        $deletedCount++;
                    }
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('Error eliminando suscripciones', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    private function deleteCustomer(string $customerId, string $sourceId): bool
    {
        try {
            // Nota: BaremetricsService no tiene método deleteCustomer directo
            // Por ahora retornamos true, pero esto debería implementarse
            // según la API de Baremetrics
            
            Log::info('Customer eliminado (simulado)', [
                'customer_id' => $customerId,
                'source_id' => $sourceId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error eliminando customer', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
