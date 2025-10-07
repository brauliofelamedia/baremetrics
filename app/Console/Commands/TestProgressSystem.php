<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use Illuminate\Console\Command;

class TestProgressSystem extends Command
{
    protected $signature = 'ghl:test-progress {comparison_id}';
    protected $description = 'Test the progress system with a sample comparison';

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        $comparison = ComparisonRecord::find($comparisonId);

        if (!$comparison) {
            $this->error("Comparación no encontrada: {$comparisonId}");
            return 1;
        }

        $this->info("Probando sistema de progreso para: {$comparison->name}");
        
        // Simular progreso paso a paso
        $steps = [
            ['Iniciando procesamiento...', 0],
            ['Leyendo archivo CSV...', 5],
            ['CSV leído exitosamente', 10],
            ['Obteniendo usuarios de Baremetrics...', 15],
            ['Obteniendo usuarios de Baremetrics... Página 1', 18],
            ['Obteniendo usuarios de Baremetrics... Página 2', 20],
            ['Usuarios de Baremetrics obtenidos', 25],
            ['Realizando comparaciones...', 30],
            ['Comparando usuarios... (50/1171)', 35],
            ['Comparando usuarios... (100/1171)', 40],
            ['Comparando usuarios... (200/1171)', 50],
            ['Comparando usuarios... (500/1171)', 60],
            ['Comparando usuarios... (800/1171)', 75],
            ['Comparando usuarios... (1000/1171)', 85],
            ['Comparando usuarios... (1171/1171)', 90],
            ['Guardando resultados...', 95],
            ['Procesamiento completado', 100],
        ];

        foreach ($steps as $index => [$step, $percentage]) {
            $this->line("Actualizando progreso: {$step} ({$percentage}%)");
            
            $data = [
                'current_step' => $step,
                'progress_percentage' => $percentage,
                'last_progress_update' => now(),
            ];

            // Simular datos específicos según el paso
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
                $data['users_found_count'] = intval($processedCount * 0.87); // 87% encontrados
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
            
            // Pausa para simular procesamiento
            usleep(500000); // 0.5 segundos
        }

        $this->info("✅ Sistema de progreso probado exitosamente!");
        $this->line("Puedes ver el progreso en: http://localhost:8000/admin/ghl-comparison/{$comparisonId}/processing");
        
        return 0;
    }
}
