<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class GetUsersWithGHLMigrateFlag extends Command
{
    protected $signature = 'baremetrics:get-ghl-migrate-users 
                           {--source-id=d9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8 : Source ID de Baremetrics}
                           {--export-csv : Exportar resultados a archivo CSV}
                           {--show-subscriptions : Mostrar suscripciones de cada usuario}
                           {--count-only : Solo mostrar el conteo total}';
    
    protected $description = 'Obtiene usuarios que tienen el campo personalizado "GHL: migrate = true" en Baremetrics';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $sourceId = $this->option('source-id');
        $exportCsv = $this->option('export-csv');
        $showSubscriptions = $this->option('show-subscriptions');
        $countOnly = $this->option('count-only');

        $this->info("🔍 BUSCANDO USUARIOS CON CAMPO 'GHL: Migrate GHL = true'");
        $this->info("=====================================================");
        $this->info("Source ID: {$sourceId}");
        $this->newLine();

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        $this->baremetricsService->reinitializeConfiguration();

        try {
            $this->info("🔍 Obteniendo todos los usuarios de Baremetrics...");
            
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

            $this->info("📊 Total de usuarios encontrados: " . count($allCustomers));
            $this->newLine();

            // Filtrar usuarios con el campo "GHL: Migrate GHL = true"
            $migrateUsers = [];
            $this->info("🔍 Filtrando usuarios con campo 'GHL: Migrate GHL = true'...");

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
            $this->info("✅ Usuarios con 'GHL: Migrate GHL = true' encontrados: {$totalMigrateUsers}");
            $this->newLine();

            if ($countOnly) {
                $this->info("📊 RESUMEN:");
                $this->info("   • Total usuarios en Baremetrics: " . count($allCustomers));
                $this->info("   • Usuarios con 'GHL: Migrate GHL = true': {$totalMigrateUsers}");
                return 0;
            }

            if ($totalMigrateUsers === 0) {
                $this->warn("⚠️ No se encontraron usuarios con el campo 'GHL: Migrate GHL = true'");
                return 0;
            }

            // Mostrar detalles de los usuarios
            $this->info("📋 DETALLES DE USUARIOS CON 'GHL: Migrate GHL = true':");
            $this->info("=====================================================");

            $csvData = [];
            $csvData[] = ['Customer ID', 'Email', 'Nombre', 'Fecha Creación', 'Suscripciones'];

            foreach ($migrateUsers as $index => $user) {
                $customerId = $user['oid'] ?? 'N/A';
                $email = $user['email'] ?? 'N/A';
                $name = $user['name'] ?? 'N/A';
                $createdAt = $user['created_at'] ?? 'N/A';
                
                $this->info("👤 Usuario #" . ($index + 1) . ":");
                $this->info("   • Customer ID: {$customerId}");
                $this->info("   • Email: {$email}");
                $this->info("   • Nombre: {$name}");
                $this->info("   • Fecha Creación: {$createdAt}");

                // Obtener suscripciones si se solicita
                $subscriptionsCount = 0;
                if ($showSubscriptions) {
                    $this->info("   • Suscripciones:");
                    $subscriptions = $this->baremetricsService->getSubscriptions($sourceId);
                    
                    if ($subscriptions && isset($subscriptions['subscriptions'])) {
                        foreach ($subscriptions['subscriptions'] as $subscription) {
                            $subscriptionCustomerOid = $subscription['customer_oid'] ?? 
                                                     $subscription['customer']['oid'] ?? 
                                                     $subscription['customerOid'] ?? 
                                                     null;
                            
                            if ($subscriptionCustomerOid === $customerId) {
                                $subscriptionsCount++;
                                $planName = $subscription['plan']['name'] ?? 'N/A';
                                $status = $subscription['status'] ?? 'N/A';
                                $this->info("     - Plan: {$planName} | Estado: {$status}");
                            }
                        }
                    }
                    
                    if ($subscriptionsCount === 0) {
                        $this->info("     - Sin suscripciones activas");
                    }
                }

                $csvData[] = [$customerId, $email, $name, $createdAt, $subscriptionsCount];
                $this->newLine();
            }

            // Exportar a CSV si se solicita
            if ($exportCsv) {
                $this->info("📁 Exportando datos a CSV...");
                $filename = 'ghl_migrate_users_' . date('Y-m-d_H-i-s') . '.csv';
                $filepath = storage_path('csv/' . $filename);
                
                // Crear directorio si no existe
                if (!file_exists(dirname($filepath))) {
                    mkdir(dirname($filepath), 0755, true);
                }

                $file = fopen($filepath, 'w');
                
                // Escribir BOM para UTF-8
                fwrite($file, "\xEF\xBB\xBF");
                
                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }
                
                fclose($file);
                
                $this->info("✅ Archivo CSV creado: {$filepath}");
            }

            // Resumen final
            $this->newLine();
            $this->info("📊 RESUMEN FINAL:");
            $this->info("==================");
            $this->info("✅ Usuarios con 'GHL: Migrate GHL = true': {$totalMigrateUsers}");
            
            if ($showSubscriptions) {
                $totalSubscriptions = array_sum(array_column(array_slice($csvData, 1), 4));
                $this->info("✅ Total de suscripciones: {$totalSubscriptions}");
            }

            $this->newLine();
            $this->info("💡 COMANDOS ÚTILES:");
            $this->info("   • Para eliminar un usuario específico:");
            $this->info("     php artisan baremetrics:delete-user {customer_id}");
            $this->info("   • Para eliminar múltiples usuarios, puedes usar este comando");
            $this->info("     junto con un script personalizado");

            // Log de la operación
            Log::info('Usuarios con GHL migrate flag obtenidos', [
                'source_id' => $sourceId,
                'total_users' => count($customers['customers']),
                'migrate_users_count' => $totalMigrateUsers,
                'export_csv' => $exportCsv,
                'show_subscriptions' => $showSubscriptions
            ]);

        } catch (\Exception $e) {
            $this->error("❌ Error durante la búsqueda: " . $e->getMessage());
            Log::error('Error obteniendo usuarios con GHL migrate flag', [
                'source_id' => $sourceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
