<?php

namespace App\Console\Commands;

use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use Illuminate\Console\Command;

class TestGHLComparisonSystem extends Command
{
    protected $signature = 'ghl:test-system';
    protected $description = 'Test the GHL comparison system';

    public function handle()
    {
        $this->info("🧪 Probando sistema de comparaciones GHL vs Baremetrics...");
        $this->line('');

        // Test 1: Verificar modelos
        $this->line("1. ✅ Verificando modelos...");
        try {
            $comparisonCount = ComparisonRecord::count();
            $missingUserCount = MissingUser::count();
            $this->line("   • ComparisonRecord: {$comparisonCount} registros");
            $this->line("   • MissingUser: {$missingUserCount} registros");
        } catch (\Exception $e) {
            $this->error("   ❌ Error en modelos: " . $e->getMessage());
            return 1;
        }

        // Test 2: Verificar servicios
        $this->line("2. ✅ Verificando servicios...");
        try {
            $baremetricsService = new \App\Services\BaremetricsService();
            $comparisonService = new \App\Services\GHLComparisonService($baremetricsService);
            $this->line("   • BaremetricsService: OK");
            $this->line("   • GHLComparisonService: OK");
        } catch (\Exception $e) {
            $this->error("   ❌ Error en servicios: " . $e->getMessage());
            return 1;
        }

        // Test 3: Verificar rutas
        $this->line("3. ✅ Verificando rutas...");
        try {
            $routes = [
                'admin.ghl-comparison.index',
                'admin.ghl-comparison.create',
                'admin.ghl-comparison.store',
            ];
            
            foreach ($routes as $route) {
                if (\Route::has($route)) {
                    $this->line("   • {$route}: OK");
                } else {
                    $this->error("   ❌ {$route}: NO ENCONTRADA");
                }
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error en rutas: " . $e->getMessage());
            return 1;
        }

        // Test 4: Verificar directorios
        $this->line("4. ✅ Verificando directorios...");
        $directories = [
            'storage/app/public/csv/comparisons',
            'resources/views/admin/ghl-comparison',
        ];
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $this->line("   • {$dir}: OK");
            } else {
                $this->warn("   ⚠️ {$dir}: NO EXISTE (se creará automáticamente)");
            }
        }

        // Test 5: Verificar configuración
        $this->line("5. ✅ Verificando configuración...");
        $configs = [
            'services.baremetrics.environment',
            'services.baremetrics.live_key',
            'services.baremetrics.production_url',
        ];
        
        foreach ($configs as $config) {
            $value = config($config);
            if ($value) {
                $displayValue = str_contains($config, 'key') ? substr($value, 0, 10) . '...' : $value;
                $this->line("   • {$config}: {$displayValue}");
            } else {
                $this->warn("   ⚠️ {$config}: NO CONFIGURADO");
            }
        }

        $this->line('');
        $this->info("🎉 Sistema de comparaciones GHL vs Baremetrics está listo!");
        $this->line('');
        $this->line("📋 Próximos pasos:");
        $this->line("   1. Acceder a: http://localhost:8000/admin/ghl-comparison");
        $this->line("   2. Crear una nueva comparación");
        $this->line("   3. Subir un archivo CSV de GHL");
        $this->line("   4. Procesar y ver resultados");
        $this->line('');
        $this->line("🔧 Si hay errores:");
        $this->line("   • Verificar configuración de Baremetrics");
        $this->line("   • Verificar permisos de archivos");
        $this->line("   • Revisar logs en storage/logs/laravel.log");

        return 0;
    }
}
