<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GHLComparisonService;
use App\Models\ComparisonRecord;
use Illuminate\Support\Facades\Storage;

class TestCSVImportExcludingStripe extends Command
{
    protected $signature = 'baremetrics:test-csv-exclude-stripe 
                           {csv_file : Ruta al archivo CSV}
                           {--name=Test CSV Sin Stripe : Nombre de la comparación}';
    
    protected $description = 'Prueba la importación de CSV excluyendo sources de Stripe para evitar timeouts';

    public function handle()
    {
        $csvFile = $this->argument('csv_file');
        $name = $this->option('name');
        
        $this->info("🧪 Probando importación de CSV excluyendo Stripe...");
        $this->info("📁 Archivo CSV: {$csvFile}");
        $this->info("📊 Nombre: {$name}");

        try {
            // Verificar que el archivo existe
            if (!file_exists($csvFile)) {
                $this->error("❌ El archivo CSV no existe: {$csvFile}");
                return 1;
            }

            // Crear registro de comparación
            $comparison = ComparisonRecord::create([
                'name' => $name,
                'csv_file_path' => $csvFile,
                'csv_file_name' => basename($csvFile),
                'status' => 'pending'
            ]);

            $this->info("✅ Comparación creada con ID: {$comparison->id}");

            // Crear un servicio personalizado que excluya Stripe
            $baremetricsService = new \App\Services\BaremetricsService();
            $ghlComparisonService = new GHLComparisonService($baremetricsService);
            
            // Modificar temporalmente el método getSources para excluir Stripe
            $this->info("🔄 Iniciando procesamiento (excluyendo Stripe)...");
            
            // Procesar manualmente para tener control sobre los sources
            $this->processComparisonExcludingStripe($comparison, $ghlComparisonService);

            // Recargar el modelo para obtener los datos actualizados
            $comparison->refresh();

            // Mostrar resultados
            $this->newLine();
            $this->info("📊 RESULTADOS DE LA COMPARACIÓN:");
            $this->info("   • Estado: {$comparison->status}");
            $this->info("   • Total usuarios GHL: {$comparison->total_ghl_users}");
            $this->info("   • Total usuarios Baremetrics: {$comparison->total_baremetrics_users}");
            $this->info("   • Usuarios encontrados: {$comparison->users_found_in_baremetrics}");
            $this->info("   • Usuarios faltantes: {$comparison->users_missing_from_baremetrics}");
            $this->info("   • Porcentaje de sincronización: {$comparison->sync_percentage}%");

            // Mostrar usuarios faltantes
            $missingUsers = $comparison->missingUsers()->where('import_status', 'pending')->get();
            if ($missingUsers->count() > 0) {
                $this->info("❌ USUARIOS REALMENTE FALTANTES ({$missingUsers->count()}):");
                foreach ($missingUsers->take(10) as $user) {
                    $this->info("   • {$user->email} - {$user->name}");
                }
                if ($missingUsers->count() > 10) {
                    $this->info("   ... y " . ($missingUsers->count() - 10) . " más");
                }
            }

            // Mostrar usuarios encontrados en otros sources
            $foundInOtherSources = $comparison->missingUsers()->where('import_status', 'found_in_other_source')->get();
            if ($foundInOtherSources->count() > 0) {
                $this->warn("⚠️  USUARIOS ENCONTRADOS EN OTROS SOURCES ({$foundInOtherSources->count()}):");
                foreach ($foundInOtherSources->take(5) as $user) {
                    $this->info("   • {$user->email} - {$user->name}");
                    $this->info("     📝 Notas: {$user->import_notes}");
                }
                if ($foundInOtherSources->count() > 5) {
                    $this->info("   ... y " . ($foundInOtherSources->count() - 5) . " más");
                }
            }

            $this->newLine();
            $this->info("🎯 COMPARACIÓN COMPLETADA EXITOSAMENTE");
            $this->info("💡 Puedes ver los detalles en: /admin/ghl-comparison/{$comparison->id}");

        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            $this->error("📋 Trace: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function processComparisonExcludingStripe(ComparisonRecord $comparison, GHLComparisonService $service)
    {
        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($service);
        
        // Leer usuarios del CSV
        $readCSVUsers = $reflection->getMethod('readCSVUsers');
        $readCSVUsers->setAccessible(true);
        $ghlUsers = $readCSVUsers->invoke($service, $comparison->csv_file_path);
        
        if (empty($ghlUsers)) {
            throw new \Exception('No se encontraron usuarios válidos en el CSV');
        }

        $this->info("📊 Usuarios GHL leídos: " . count($ghlUsers));

        // Obtener sources excluyendo Stripe
        config(['services.baremetrics.environment' => 'production']);
        $baremetricsService = new \App\Services\BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $sourcesResponse = $baremetricsService->getSources();
        $sources = array_filter($sourcesResponse['sources'] ?? [], function($source) {
            return ($source['provider'] ?? '') !== 'stripe';
        });

        $this->info("📊 Sources encontrados (excluyendo Stripe): " . count($sources));

        // Obtener usuarios de Baremetrics de sources no-Stripe
        $allUsers = [];
        foreach ($sources as $source) {
            $sourceId = $source['id'];
            $provider = $source['provider'] ?? 'unknown';
            $this->info("🔍 Procesando source: {$provider}");
            
            $page = 1;
            $hasMore = true;
            $maxPages = 10; // Límite más bajo para prueba

            while ($hasMore && $page <= $maxPages) {
                $customersResponse = $baremetricsService->getCustomers($sourceId, '', $page);
                
                if (!$customersResponse || !isset($customersResponse['customers'])) {
                    break;
                }

                $customers = $customersResponse['customers'];
                if (empty($customers)) {
                    break;
                }

                foreach ($customers as $customer) {
                    $email = strtolower(trim($customer['email'] ?? ''));
                    if (!empty($email)) {
                        if (!isset($allUsers[$email])) {
                            $allUsers[$email] = [
                                'email' => $email,
                                'id' => $customer['oid'] ?? $customer['id'] ?? null,
                                'name' => $customer['name'] ?? '',
                                'sources' => []
                            ];
                        }
                        
                        $allUsers[$email]['sources'][] = [
                            'source_id' => $sourceId,
                            'provider' => $provider,
                            'customer_oid' => $customer['oid'] ?? $customer['id'] ?? null
                        ];
                    }
                }

                $hasMore = isset($customersResponse['meta']['pagination']) && 
                          ($customersResponse['meta']['pagination']['has_more'] ?? false);
                $page++;
            }
        }

        $baremetricsUsers = array_values($allUsers);
        $this->info("📊 Usuarios Baremetrics obtenidos: " . count($baremetricsUsers));

        // Comparar usuarios
        $compareUsers = $reflection->getMethod('compareUsers');
        $compareUsers->setAccessible(true);
        $comparisonResult = $compareUsers->invoke($service, $ghlUsers, $baremetricsUsers, $comparison);

        // Guardar resultados
        $saveResults = $reflection->getMethod('saveComparisonResults');
        $saveResults->setAccessible(true);
        $saveResults->invoke($service, $comparison, $comparisonResult);

        $comparison->update([
            'status' => 'completed',
            'processed_at' => now()
        ]);
    }
}
