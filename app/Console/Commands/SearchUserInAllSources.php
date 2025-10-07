<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class SearchUserInAllSources extends Command
{
    protected $signature = 'baremetrics:search-all-sources {email}';
    protected $description = 'Busca un usuario en todos los sources de Baremetrics';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔍 Buscando usuario en TODOS los sources: {$email}");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();

        try {
            // 1. Obtener todos los sources
            $this->info("📋 Obteniendo sources disponibles...");
            $sourcesResponse = $baremetricsService->getSources();
            
            if (!$sourcesResponse || !isset($sourcesResponse['sources'])) {
                $this->error("❌ No se pudieron obtener los sources");
                return;
            }

            $sources = $sourcesResponse['sources'];
            $this->info("📊 Encontrados " . count($sources) . " sources");

            $foundInSources = [];
            $totalFound = 0;

            // 2. Buscar en cada source
            foreach ($sources as $source) {
                $sourceId = $source['id'];
                $provider = $source['provider'] ?? 'unknown';
                
                $this->info("🔍 Buscando en source: {$sourceId} (Provider: {$provider})");
                
                $customers = $baremetricsService->getCustomers($sourceId);
                
                if ($customers && isset($customers['customers'])) {
                    $found = false;
                    foreach ($customers['customers'] as $customer) {
                        if (strtolower($customer['email']) === strtolower($email)) {
                            $this->info("  ✅ ENCONTRADO: {$customer['oid']} - {$customer['name']}");
                            $foundInSources[] = [
                                'source_id' => $sourceId,
                                'provider' => $provider,
                                'customer_oid' => $customer['oid'],
                                'name' => $customer['name'],
                                'email' => $customer['email']
                            ];
                            $totalFound++;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $this->info("  ❌ No encontrado");
                    }
                } else {
                    $this->warn("  ⚠️ Error obteniendo clientes del source");
                }
            }

            // 3. Resumen final
            $this->info("📊 RESUMEN FINAL:");
            $this->info("   • Total sources verificados: " . count($sources));
            $this->info("   • Usuario encontrado en: {$totalFound} sources");
            
            if ($totalFound > 0) {
                $this->warn("⚠️  USUARIO DUPLICADO EN MÚLTIPLES SOURCES:");
                foreach ($foundInSources as $found) {
                    $this->info("   • Source: {$found['source_id']} ({$found['provider']})");
                    $this->info("     Customer: {$found['customer_oid']} - {$found['name']}");
                }
                
                if ($totalFound > 1) {
                    $this->error("❌ PROBLEMA: Usuario existe en {$totalFound} sources diferentes");
                    $this->info("💡 Recomendación: Usar comando de limpieza para eliminar duplicados");
                }
            } else {
                $this->info("✅ Usuario NO encontrado en ningún source - Listo para importar");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante la búsqueda: " . $e->getMessage());
            Log::error('Error buscando usuario en todos los sources', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
