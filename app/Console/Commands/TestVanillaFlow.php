<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use Illuminate\Console\Command;

class TestVanillaFlow extends Command
{
    protected $signature = 'ghl:test-vanilla-flow';
    protected $description = 'Test the vanilla JavaScript flow';

    public function handle()
    {
        $this->info("🧪 Probando flujo con JavaScript vanilla...");
        $this->line('');

        // Crear comparación
        $comparison = ComparisonRecord::create([
            'name' => 'Prueba Vanilla JS ' . now()->format('H:i:s'),
            'csv_file_path' => 'test_vanilla.csv',
            'csv_file_name' => 'test_vanilla.csv',
            'status' => 'pending',
        ]);
        
        $this->line("✅ Comparación creada con ID: {$comparison->id}");
        $this->line("✅ Estado inicial: {$comparison->status}");
        $this->line('');

        // URLs de prueba
        $this->line("📋 URLs para probar:");
        $this->line("   • Vista de procesamiento: " . route('admin.ghl-comparison.processing', $comparison->id));
        $this->line("   • Endpoint de progreso: " . route('admin.ghl-comparison.progress', $comparison->id));
        $this->line("   • Iniciar procesamiento: " . route('admin.ghl-comparison.start-processing', $comparison->id));
        $this->line('');

        // Simular progreso gradual
        $this->line("🔄 Simulando progreso gradual...");
        $this->simulateGradualProgress($comparison);

        $this->line('');
        $this->info("🎉 Prueba completada!");
        $this->line('');
        $this->line("🔧 Instrucciones:");
        $this->line("   1. Abre la URL de procesamiento en el navegador");
        $this->line("   2. Abre la consola del navegador (F12)");
        $this->line("   3. Observa los logs de JavaScript");
        $this->line("   4. Verifica que el progreso se actualice automáticamente");
        $this->line("   5. Confirma que no hay errores de jQuery");

        return 0;
    }

    private function simulateGradualProgress(ComparisonRecord $comparison)
    {
        $steps = [
            ['Iniciando procesamiento...', 0],
            ['Leyendo archivo CSV...', 5],
            ['CSV leído exitosamente', 10],
            ['Obteniendo usuarios de Baremetrics...', 15],
            ['Usuarios de Baremetrics obtenidos', 25],
            ['Realizando comparaciones...', 30],
            ['Comparando usuarios... (100/1171)', 40],
            ['Comparando usuarios... (500/1171)', 60],
            ['Comparando usuarios... (1000/1171)', 80],
            ['Comparando usuarios... (1171/1171)', 90],
            ['Guardando resultados...', 95],
            ['Procesamiento completado', 100],
        ];

        foreach ($steps as $index => [$step, $percentage]) {
            $this->line("   • {$step} ({$percentage}%)");
            
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
            
            // Pausa más larga para que el usuario pueda ver el progreso
            usleep(1000000); // 1 segundo
        }
    }
}
