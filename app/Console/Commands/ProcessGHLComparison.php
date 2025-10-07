<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use App\Services\GHLComparisonService;
use Illuminate\Console\Command;

class ProcessGHLComparison extends Command
{
    protected $signature = 'ghl:process-comparison {comparison_id}';
    protected $description = 'Process a GHL comparison in background';

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        
        $comparison = ComparisonRecord::find($comparisonId);
        
        if (!$comparison) {
            $this->error("Comparación no encontrada: {$comparisonId}");
            return 1;
        }

        $this->info("Procesando comparación: {$comparison->name}");
        
        try {
            $comparisonService = new GHLComparisonService(new \App\Services\BaremetricsService());
            $comparisonService->processComparison($comparison);
            
            $this->info("Comparación procesada exitosamente");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error procesando comparación: " . $e->getMessage());
            return 1;
        }
    }
}