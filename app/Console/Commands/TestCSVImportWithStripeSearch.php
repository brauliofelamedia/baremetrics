<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GHLComparisonService;
use App\Models\ComparisonRecord;
use Illuminate\Support\Facades\Storage;

class TestCSVImportWithStripeSearch extends Command
{
    protected $signature = 'baremetrics:test-csv-with-stripe-search 
                           {csv_file : Ruta al archivo CSV}
                           {--name=Test CSV Con Stripe : Nombre de la comparación}';
    
    protected $description = 'Prueba la importación de CSV con búsquedas específicas en Stripe';

    public function handle()
    {
        $csvFile = $this->argument('csv_file');
        $name = $this->option('name');
        
        $this->info("🧪 Probando importación de CSV con búsquedas específicas en Stripe...");
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

            // Procesar la comparación con la nueva lógica
            $this->info("🔄 Iniciando procesamiento con nueva lógica...");
            $ghlComparisonService = new GHLComparisonService(new \App\Services\BaremetricsService());
            $ghlComparisonService->processComparison($comparison);

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
}
