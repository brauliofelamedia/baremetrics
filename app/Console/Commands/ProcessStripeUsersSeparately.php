<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Models\ComparisonRecord;
use App\Models\MissingUser;
use Illuminate\Support\Facades\Log;

class ProcessStripeUsersSeparately extends Command
{
    protected $signature = 'baremetrics:process-stripe-users 
                           {comparison_id : ID de la comparación}
                           {--limit=100 : Límite de usuarios a procesar}';
    
    protected $description = 'Procesa usuarios de Stripe de forma separada y controlada';

    public function handle()
    {
        $comparisonId = $this->argument('comparison_id');
        $limit = (int) $this->option('limit');
        
        $this->info("🔍 Procesando usuarios de Stripe de forma separada...");
        $this->info("📊 Comparación ID: {$comparisonId}");
        $this->info("📊 Límite: {$limit} usuarios");

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

            // 3. Obtener sources de Stripe
            $sourcesResponse = $baremetricsService->getSources();
            $stripeSources = array_filter($sourcesResponse['sources'] ?? [], function($source) {
                return ($source['provider'] ?? '') === 'stripe';
            });

            if (empty($stripeSources)) {
                $this->warn("⚠️  No se encontraron sources de Stripe");
                return 0;
            }

            $this->info("📊 Encontrados " . count($stripeSources) . " sources de Stripe");

            // 4. Verificar cada usuario faltante contra sources de Stripe
            $foundInStripe = [];
            $stillMissing = [];

            $progressBar = $this->output->createProgressBar($missingUsers->count());
            $progressBar->start();

            foreach ($missingUsers as $missingUser) {
                $email = $missingUser->email;
                $foundInSources = [];
                
                // Buscar en cada source de Stripe
                foreach ($stripeSources as $source) {
                    $sourceId = $source['id'];
                    $customers = $baremetricsService->getCustomers($sourceId, $email);
                    
                    if ($customers && isset($customers['customers'])) {
                        foreach ($customers['customers'] as $customer) {
                            if (strtolower($customer['email']) === strtolower($email)) {
                                $foundInSources[] = [
                                    'source_id' => $sourceId,
                                    'provider' => $source['provider'] ?? 'stripe',
                                    'customer_oid' => $customer['oid'],
                                    'name' => $customer['name']
                                ];
                                break;
                            }
                        }
                    }
                }

                // Clasificar usuario
                if (!empty($foundInSources)) {
                    $foundInStripe[] = [
                        'missing_user' => $missingUser,
                        'stripe_sources' => $foundInSources
                    ];
                } else {
                    $stillMissing[] = $missingUser;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // 5. Mostrar resultados
            $this->info("📊 RESULTADOS DE LA VERIFICACIÓN EN STRIPE:");
            $this->info("   • Usuarios encontrados en Stripe: " . count($foundInStripe));
            $this->info("   • Usuarios realmente faltantes: " . count($stillMissing));

            // 6. Actualizar estados en la base de datos
            foreach ($foundInStripe as $found) {
                $user = $found['missing_user'];
                $stripeSources = $found['stripe_sources'];
                
                $user->update([
                    'import_status' => 'found_in_other_source',
                    'baremetrics_customer_id' => $stripeSources[0]['customer_oid'],
                    'import_notes' => 'Usuario encontrado en Stripe: ' . implode(', ', array_column($stripeSources, 'source_id'))
                ]);
                
                $this->info("✅ {$user->email} - Encontrado en Stripe");
            }

            // 7. Resumen final
            $this->newLine();
            $this->info("🎯 RESUMEN FINAL:");
            $this->info("   • Total usuarios verificados: " . $missingUsers->count());
            $this->info("   • Encontrados en Stripe: " . count($foundInStripe));
            $this->info("   • Realmente faltantes: " . count($stillMissing));

            if (count($stillMissing) > 0) {
                $this->info("❌ USUARIOS REALMENTE FALTANTES:");
                foreach ($stillMissing as $user) {
                    $this->info("   • {$user->email} - {$user->name}");
                    $this->info("     💡 Comando: php artisan baremetrics:complete-test-import {$user->email}");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante el procesamiento: " . $e->getMessage());
            Log::error('Error procesando usuarios de Stripe', [
                'comparison_id' => $comparisonId,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
