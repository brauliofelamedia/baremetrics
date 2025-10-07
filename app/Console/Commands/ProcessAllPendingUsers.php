<?php

namespace App\Console\Commands;
   
use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use Illuminate\Support\Facades\Log;

class ProcessAllPendingUsers extends Command
{
    protected $signature = 'baremetrics:process-all-pending-users 
                           {comparison_id : ID de la comparación}
                           {--limit=100 : Límite de usuarios a procesar}
                           {--batch-size=5 : Procesar en lotes de N usuarios}
                           {--delay=2 : Delay entre usuarios en segundos}';
    
    protected $description = 'Procesa todos los usuarios pendientes usando el comando de importación completa';

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        
        $this->info("🚀 PROCESANDO TODOS LOS USUARIOS PENDIENTES");
        $this->info("==========================================");
        $this->info("📊 Comparación ID: {$comparisonId}");
        $this->info("📊 Límite: {$limit} usuarios");
        $this->info("📊 Tamaño de lote: {$batchSize}");
        $this->info("📊 Delay entre usuarios: {$delay} segundos");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();

        try {
            // 1. Obtener la comparación
            $comparison = ComparisonRecord::find($comparisonId);
            if (!$comparison) {
                $this->error("❌ Comparación no encontrada: {$comparisonId}");
                return 1;
            }

            $this->info("✅ Comparación encontrada: {$comparison->name}");

            // 2. Obtener usuarios faltantes pendientes
            $missingUsers = $comparison->missingUsers()
                ->where('import_status', 'pending')
                ->limit($limit)
                ->get();

            if ($missingUsers->isEmpty()) {
                $this->error("❌ No se encontraron usuarios faltantes pendientes");
                return 1;
            }

            $this->info("📊 Encontrados " . $missingUsers->count() . " usuarios faltantes pendientes");

            // 3. Procesar usuarios en lotes
            $totalProcessed = 0;
            $successfulImports = 0;
            $failedImports = 0;
            $skippedUsers = 0;

            $progressBar = $this->output->createProgressBar($missingUsers->count());
            $progressBar->start();

            foreach ($missingUsers as $missingUser) {
                $email = $missingUser->email;
                $name = $missingUser->name;
                
                $this->newLine();
                $this->info("🔄 Procesando: {$email} - {$name}");
                
                try {
                    // Ejecutar el comando de importación completa
                    $exitCode = $this->call('baremetrics:import-user-complete', [
                        'email' => $email
                    ]);

                    if ($exitCode === 0) {
                        $this->info("✅ Importación exitosa: {$email}");
                        $successfulImports++;
                        
                        // Marcar como importado
                        $missingUser->update([
                            'import_status' => 'imported',
                            'imported_at' => now(),
                            'notes' => 'Importado exitosamente con comando completo'
                        ]);
                    } else {
                        $this->error("❌ Error en importación: {$email}");
                        $failedImports++;
                        
                        // Marcar como error
                        $missingUser->update([
                            'import_status' => 'error',
                            'notes' => 'Error durante la importación'
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Excepción durante importación: {$email} - " . $e->getMessage());
                    $failedImports++;
                    
                    // Marcar como error
                    $missingUser->update([
                        'import_status' => 'error',
                        'notes' => 'Excepción: ' . $e->getMessage()
                    ]);
                    
                    Log::error('Error procesando usuario pendiente', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                $totalProcessed++;
                $progressBar->advance();

                // Delay entre usuarios
                if ($delay > 0 && $totalProcessed < $missingUsers->count()) {
                    sleep($delay);
                }

                // Delay entre lotes
                if ($totalProcessed % $batchSize === 0 && $totalProcessed < $missingUsers->count()) {
                    $this->newLine();
                    $this->info("⏸️ Pausa entre lotes...");
                    sleep(5);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // 4. Resumen final
            $this->info("🎉 PROCESAMIENTO COMPLETADO");
            $this->info("==========================");
            $this->info("📊 Total procesados: {$totalProcessed}");
            $this->info("✅ Importaciones exitosas: {$successfulImports}");
            $this->info("❌ Importaciones fallidas: {$failedImports}");
            $this->info("⏭️ Usuarios omitidos: {$skippedUsers}");
            
            if ($successfulImports > 0) {
                $this->info("🎯 Tasa de éxito: " . round(($successfulImports / $totalProcessed) * 100, 2) . "%");
            }

            // 5. Actualizar estado de la comparación
            $remainingPending = $comparison->missingUsers()
                ->where('import_status', 'pending')
                ->count();
                
            $this->info("📋 Usuarios pendientes restantes: {$remainingPending}");

            if ($remainingPending === 0) {
                $this->info("🎉 ¡Todos los usuarios han sido procesados!");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante el procesamiento: " . $e->getMessage());
            Log::error('Error en procesamiento masivo de usuarios pendientes', [
                'comparison_id' => $comparisonId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }
}
