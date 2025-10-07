<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use Illuminate\Support\Facades\Log;

class VerifyMissingUsersAgainstAllSources extends Command
{
    protected $signature = 'baremetrics:verify-missing-users 
                           {comparison_id : ID de la comparación a verificar}
                           {--limit=50 : Límite de usuarios a verificar}
                           {--dry-run : Solo mostrar resultados sin hacer cambios}';
    
    protected $description = 'Verifica usuarios faltantes del CSV contra TODOS los sources de Baremetrics';

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        $limit = (int) $this->option('limit');
        $isDryRun = $this->option('dry-run');
        
        $this->info("🔍 Verificando usuarios faltantes contra TODOS los sources...");
        $this->info("📊 Comparación ID: {$comparisonId}");
        $this->info("📊 Límite: {$limit} usuarios");
        
        if ($isDryRun) {
            $this->warn("⚠️  MODO DRY-RUN: Solo análisis, sin cambios");
        }

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();

        try {
            // 1. Obtener la comparación
            $comparison = ComparisonRecord::find($comparisonId);
            if (!$comparison) {
                $this->error("❌ Comparación no encontrada: {$comparisonId}");
                return;
            }

            $this->info("✅ Comparación encontrada: {$comparison->name}");

            // 2. Obtener usuarios faltantes
            $missingUsers = $comparison->missingUsers()
                ->where('import_status', 'pending')
                ->limit($limit)
                ->get();

            if ($missingUsers->isEmpty()) {
                $this->error("❌ No se encontraron usuarios faltantes pendientes");
                return;
            }

            $this->info("📊 Encontrados " . $missingUsers->count() . " usuarios faltantes pendientes");

            // 3. Obtener todos los sources de Baremetrics
            $this->info("📋 Obteniendo sources de Baremetrics...");
            $sourcesResponse = $baremetricsService->getSources();
            
            if (!$sourcesResponse || !isset($sourcesResponse['sources'])) {
                $this->error("❌ No se pudieron obtener los sources");
                return;
            }

            $sources = $sourcesResponse['sources'];
            $this->info("📊 Encontrados " . count($sources) . " sources en Baremetrics");

            // 4. Verificar cada usuario faltante
            $actuallyMissing = [];
            $foundInOtherSources = [];
            $duplicatesFound = [];

            $progressBar = $this->output->createProgressBar($missingUsers->count());
            $progressBar->start();

            foreach ($missingUsers as $missingUser) {
                $email = $missingUser->email;
                $foundInSources = [];
                
                // Buscar en cada source
                foreach ($sources as $source) {
                    $sourceId = $source['id'];
                    $customers = $baremetricsService->getCustomers($sourceId);
                    
                    if ($customers && isset($customers['customers'])) {
                        foreach ($customers['customers'] as $customer) {
                            if (strtolower($customer['email']) === strtolower($email)) {
                                $foundInSources[] = [
                                    'source_id' => $sourceId,
                                    'provider' => $source['provider'] ?? 'unknown',
                                    'customer_oid' => $customer['oid'],
                                    'name' => $customer['name']
                                ];
                                break;
                            }
                        }
                    }
                }

                // Clasificar usuario
                if (empty($foundInSources)) {
                    $actuallyMissing[] = $missingUser;
                } elseif (count($foundInSources) === 1) {
                    $foundInOtherSources[] = [
                        'missing_user' => $missingUser,
                        'baremetrics' => $foundInSources[0]
                    ];
                } else {
                    $duplicatesFound[] = [
                        'missing_user' => $missingUser,
                        'baremetrics' => $foundInSources
                    ];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // 5. Mostrar resultados
            $this->info("📊 RESULTADOS DE LA VERIFICACIÓN:");
            $this->info("   • Usuarios realmente faltantes: " . count($actuallyMissing));
            $this->info("   • Usuarios encontrados en otros sources: " . count($foundInOtherSources));
            $this->info("   • Usuarios con duplicados: " . count($duplicatesFound));

            // 6. Mostrar usuarios realmente faltantes
            if (!empty($actuallyMissing)) {
                $this->info("❌ USUARIOS REALMENTE FALTANTES:");
                foreach ($actuallyMissing as $user) {
                    $this->info("   • {$user->email} - {$user->name}");
                    if (!$isDryRun) {
                        $this->info("     💡 Comando: php artisan baremetrics:complete-test-import {$user->email}");
                    }
                }
            }

            // 7. Mostrar usuarios encontrados en otros sources
            if (!empty($foundInOtherSources)) {
                $this->warn("⚠️  USUARIOS ENCONTRADOS EN OTROS SOURCES:");
                foreach ($foundInOtherSources as $found) {
                    $user = $found['missing_user'];
                    $bm = $found['baremetrics'];
                    $this->info("   • {$user->email} - {$user->name}");
                    $this->info("     ✅ Encontrado en: {$bm['source_id']} ({$bm['provider']}) - {$bm['customer_oid']}");
                    
                    if (!$isDryRun) {
                        // Actualizar estado del usuario faltante
                        $user->update([
                            'import_status' => 'found_in_other_source',
                            'baremetrics_customer_id' => $bm['customer_oid'],
                            'import_notes' => "Usuario encontrado en source {$bm['provider']}: {$bm['source_id']}"
                        ]);
                        $this->info("     ✅ Estado actualizado en base de datos");
                    }
                }
            }

            // 8. Mostrar usuarios con duplicados
            if (!empty($duplicatesFound)) {
                $this->error("❌ USUARIOS CON DUPLICADOS:");
                foreach ($duplicatesFound as $duplicate) {
                    $user = $duplicate['missing_user'];
                    $this->info("   • {$user->email} - {$user->name}");
                    foreach ($duplicate['baremetrics'] as $bm) {
                        $this->info("     - {$bm['source_id']} ({$bm['provider']}) - {$bm['customer_oid']}");
                    }
                    if (!$isDryRun) {
                        $this->info("     💡 Comando: php artisan baremetrics:cleanup-duplicate-user {$user->email}");
                    }
                }
            }

            // 9. Resumen final
            $this->newLine();
            $this->info("🎯 RESUMEN FINAL:");
            $this->info("   • Total usuarios verificados: " . $missingUsers->count());
            $this->info("   • Realmente faltantes: " . count($actuallyMissing));
            $this->info("   • Encontrados en otros sources: " . count($foundInOtherSources));
            $this->info("   • Con duplicados: " . count($duplicatesFound));

            if (!$isDryRun && !empty($foundInOtherSources)) {
                $this->info("✅ Estados actualizados en base de datos");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante la verificación: " . $e->getMessage());
            Log::error('Error verificando usuarios faltantes', [
                'comparison_id' => $comparisonId,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
