<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use Illuminate\Console\Command;

class CreateTestComparison extends Command
{
    protected $signature = 'ghl:create-test-comparison';
    protected $description = 'Create a test comparison for debugging';

    public function handle()
    {
        $comparison = ComparisonRecord::create([
            'name' => 'Prueba Progreso ' . now()->format('H:i:s'),
            'csv_file_path' => 'test.csv',
            'csv_file_name' => 'test.csv',
            'status' => 'pending',
        ]);

        $this->info("Comparación de prueba creada con ID: {$comparison->id}");
        $this->line("URL de procesamiento: http://localhost:8000/admin/ghl-comparison/{$comparison->id}/processing");
        
        return 0;
    }
}
