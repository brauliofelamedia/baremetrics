<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use Illuminate\Console\Command;

class TestCompleteFlow extends Command
{
    protected $signature = 'ghl:test-complete-flow';
    protected $description = 'Test the complete flow from creation to processing';

    public function handle()
    {
        $this->info("🧪 Probando flujo completo del sistema de comparaciones...");
        $this->line('');

        // Paso 1: Crear comparación
        $this->line("1. ✅ Creando comparación de prueba...");
        $comparison = ComparisonRecord::create([
            'name' => 'Prueba Flujo Completo ' . now()->format('H:i:s'),
            'csv_file_path' => 'test_complete.csv',
            'csv_file_name' => 'test_complete.csv',
            'status' => 'pending',
        ]);
        
        $this->line("   • Comparación creada con ID: {$comparison->id}");
        $this->line("   • Estado inicial: {$comparison->status}");

        // Paso 2: Verificar rutas
        $this->line("2. ✅ Verificando rutas...");
        $routes = [
            'admin.ghl-comparison.processing' => route('admin.ghl-comparison.processing', $comparison->id),
            'admin.ghl-comparison.start-processing' => route('admin.ghl-comparison.start-processing', $comparison->id),
            'admin.ghl-comparison.progress' => route('admin.ghl-comparison.progress', $comparison->id),
        ];
        
        foreach ($routes as $name => $url) {
            $this->line("   • {$name}: {$url}");
        }

        // Paso 3: Simular procesamiento
        $this->line("3. ✅ Simulando procesamiento...");
        $this->simulateProcessing($comparison);

        $this->line('');
        $this->info("🎉 Flujo completo probado exitosamente!");
        $this->line('');
        $this->line("📋 URLs para probar:");
        $this->line("   • Vista de procesamiento: " . route('admin.ghl-comparison.processing', $comparison->id));
        $this->line("   • Ver resultados: " . route('admin.ghl-comparison.show', $comparison->id));
        $this->line('');
        $this->line("🔧 Para probar manualmente:");
        $this->line("   1. Accede a la URL de procesamiento");
        $this->line("   2. Observa el progreso en tiempo real");
        $this->line("   3. Verifica que se complete correctamente");

        return 0;
    }

    private function simulateProcessing(ComparisonRecord $comparison)
    {
        $steps = [
            ['Iniciando procesamiento...', 0],
            ['Leyendo archivo CSV...', 5],
            ['CSV leído exitosamente', 10],
            ['Obteniendo usuarios de Baremetrics...', 15],
            ['Usuarios de Baremetrics obtenidos', 25],
            ['Realizando comparaciones...', 30],
            ['Comparando usuarios... (500/1171)', 50],
            ['Comparando usuarios... (1000/1171)', 75],
            ['Comparando usuarios... (1171/1171)', 90],
            ['Guardando resultados...', 95],
            ['Procesamiento completado', 100],
        ];

        foreach ($steps as [$step, $percentage]) {
            $data = [
                'current_step' => $step,
                'progress_percentage' => $percentage,
                'last_progress_update' => now(),
            ];

            if ($percentage >= 10) {
                $data['total_rows_processed'] = 1171;
                $data['total_ghl_users'] = 1171;
            }
            
            if ($percentage >= 25) {
                $data['baremetrics_users_fetched'] = 2500;
                $data['total_baremetrics_users'] = 2500;
            }
            
            if ($percentage >= 30) {
                $processedCount = min(1171, intval(($percentage - 30) / 60 * 1171));
                $data['ghl_users_processed'] = $processedCount;
                $data['comparisons_made'] = $processedCount;
                $data['users_found_count'] = intval($processedCount * 0.87);
                $data['users_missing_count'] = $processedCount - intval($processedCount * 0.87);
            }
            
            if ($percentage == 100) {
                $data['status'] = 'completed';
                $data['processed_at'] = now();
                $data['users_found_in_baremetrics'] = 1019;
                $data['users_missing_from_baremetrics'] = 152;
                $data['sync_percentage'] = 87.02;
            }

            $comparison->updateProgress($step, $percentage, $data);
            $this->line("   • {$step} ({$percentage}%)");
            
            usleep(200000); // 0.2 segundos
        }
    }
}
