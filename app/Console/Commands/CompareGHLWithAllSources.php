<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class CompareGHLWithAllSources extends Command
{
    protected $signature = 'baremetrics:compare-ghl-all-sources {email}';
    protected $description = 'Compara un usuario de GHL contra TODOS los sources de Baremetrics';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔍 Comparando usuario GHL contra TODOS los sources: {$email}");

        // Configurar para producción
        config(['services.baremetrics.environment' => 'production']);
        
        $baremetricsService = new BaremetricsService();
        $baremetricsService->reinitializeConfiguration();
        
        $ghlService = new GoHighLevelService();

        try {
            // 1. Verificar si existe en GHL
            $this->info("📡 Verificando existencia en GHL...");
            $ghlResponse = $ghlService->getContacts($email);
            
            if (empty($ghlResponse) || !isset($ghlResponse['contacts']) || empty($ghlResponse['contacts'])) {
                $this->error("❌ Usuario NO encontrado en GHL");
                return;
            }

            $contact = $ghlResponse['contacts'][0];
            $this->info("✅ Usuario encontrado en GHL: {$contact['firstName']} {$contact['lastName']}");

            // 2. Obtener todos los sources de Baremetrics
            $this->info("📋 Obteniendo sources de Baremetrics...");
            $sourcesResponse = $baremetricsService->getSources();
            
            if (!$sourcesResponse || !isset($sourcesResponse['sources'])) {
                $this->error("❌ No se pudieron obtener los sources");
                return;
            }

            $sources = $sourcesResponse['sources'];
            $this->info("📊 Encontrados " . count($sources) . " sources");

            // 3. Buscar en cada source
            $foundInSources = [];
            $totalFound = 0;

            foreach ($sources as $source) {
                $sourceId = $source['id'];
                $provider = $source['provider'] ?? 'unknown';
                
                $this->info("🔍 Buscando en source: {$sourceId} ({$provider})");
                
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

            // 4. Análisis y recomendaciones
            $this->info("📊 ANÁLISIS COMPLETO:");
            $this->info("   • Usuario en GHL: ✅ SÍ");
            $this->info("   • Usuario en Baremetrics: " . ($totalFound > 0 ? "✅ SÍ ({$totalFound} sources)" : "❌ NO"));
            
            if ($totalFound === 0) {
                $this->info("✅ RECOMENDACIÓN: Usuario listo para importar");
                $this->info("💡 Puedes usar: php artisan baremetrics:complete-test-import {$email}");
            } elseif ($totalFound === 1) {
                $found = $foundInSources[0];
                $this->info("ℹ️  Usuario ya existe en 1 source:");
                $this->info("   • Source: {$found['source_id']} ({$found['provider']})");
                $this->info("   • Customer: {$found['customer_oid']}");
                
                if ($found['provider'] === 'baremetrics') {
                    $this->info("✅ Usuario en source manual - Puede actualizar custom fields");
                    $this->info("💡 Puedes usar: php artisan baremetrics:update-custom-fields {$email}");
                } else {
                    $this->warn("⚠️  Usuario en source de Stripe - Verificar si necesita migración");
                }
            } else {
                $this->error("❌ PROBLEMA: Usuario duplicado en {$totalFound} sources:");
                foreach ($foundInSources as $found) {
                    $this->info("   • {$found['source_id']} ({$found['provider']}) - {$found['customer_oid']}");
                }
                $this->info("💡 Recomendación: Usar comando de limpieza para eliminar duplicados");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error durante la comparación: " . $e->getMessage());
            Log::error('Error comparando GHL con todos los sources', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
