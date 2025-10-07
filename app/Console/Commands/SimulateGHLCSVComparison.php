<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class SimulateGHLCSVComparison extends Command
{
    protected $signature = 'ghl:simulate-csv-comparison {--users=1171} {--tags=creetelo_mensual,creetelo_anual} {--exclude-tags=unsubscribe}';
    protected $description = 'Simulate the CSV comparison with the expected number of users';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    public function handle()
    {
        $totalUsers = (int) $this->option('users');
        $tagsInput = $this->option('tags');
        $excludeTagsInput = $this->option('exclude-tags');

        $tags = array_map('trim', explode(',', $tagsInput));
        $excludeTags = array_map('trim', explode(',', $excludeTagsInput));

        $this->info("🔍 Simulando comparación GHL CSV vs Baremetrics...");
        $this->line('');
        $this->info("📋 Configuración:");
        $this->line("   • Total usuarios GHL esperados: {$totalUsers}");
        $this->line("   • Tags incluidos: " . implode(', ', $tags));
        $this->line("   • Tags excluidos: " . implode(', ', $excludeTags));
        $this->line('');

        // Simular distribución de usuarios por tags
        $tagDistribution = [
            'creetelo_mensual' => 0.4,  // 40% de usuarios
            'creetelo_anual' => 0.35,   // 35% de usuarios
            'créetelo_mensual' => 0.15, // 15% de usuarios
            'créetelo_anual' => 0.1,    // 10% de usuarios
        ];

        $this->line("🔍 Simulando distribución de usuarios por tags...");
        $simulatedUsers = [];
        $userCount = 0;

        foreach ($tagDistribution as $tag => $percentage) {
            $count = (int) ($totalUsers * $percentage);
            $this->line("   • {$tag}: {$count} usuarios");
            
            for ($i = 0; $i < $count; $i++) {
                $userCount++;
                $simulatedUsers[] = [
                    'id' => "ghl_{$userCount}",
                    'name' => "Usuario {$userCount}",
                    'email' => "usuario{$userCount}@example.com",
                    'tags' => [$tag],
                    'phone' => "+123456789{$userCount}",
                    'company' => "Empresa {$userCount}",
                ];
            }
        }

        $this->line("   • Total usuarios simulados: " . count($simulatedUsers));
        $this->line('');

        // Obtener emails reales de Baremetrics
        $this->line("🔍 Obteniendo emails reales de Baremetrics...");
        $baremetricsEmails = $this->getBaremetricsEmails();
        $this->line("✅ Encontrados " . count($baremetricsEmails) . " emails en Baremetrics");
        $this->line('');

        // Simular usuarios que están en ambos sistemas (10% de coincidencia)
        $commonCount = (int) (count($simulatedUsers) * 0.1);
        $missingCount = count($simulatedUsers) - $commonCount;

        $this->line("🔄 Analizando usuarios...");
        $this->line('');

        // Mostrar resultados simulados
        $this->showSimulatedResults($simulatedUsers, $baremetricsEmails, $commonCount, $missingCount);

        $this->line('');
        $this->info("💡 Esta es una simulación basada en los datos esperados.");
        $this->line("   Para obtener resultados reales, sube el archivo CSV a storage/csv/creetelo_ghl.csv");
        $this->line("   y ejecuta: php artisan ghl:compare-csv --file=storage/csv/creetelo_ghl.csv --save");
    }

    private function getBaremetricsEmails()
    {
        $emails = [];
        
        try {
            $sources = $this->baremetricsService->getSources();
            
            if (empty($sources) || !isset($sources['sources'])) {
                $this->warn("   ⚠️ No se encontraron fuentes en Baremetrics");
                return $emails;
            }

            foreach ($sources['sources'] as $source) {
                $sourceId = $source['id'];
                $this->line("   📄 Procesando source: {$sourceId}");
                
                $customers = $this->baremetricsService->getCustomersAll($sourceId);
                
                if (!empty($customers)) {
                    foreach ($customers as $customer) {
                        if (!empty($customer['email'])) {
                            $emails[] = strtolower(trim($customer['email']));
                        }
                    }
                }
                
                $this->line("     • {$sourceId}: " . count($customers) . " customers");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error obteniendo emails de Baremetrics: " . $e->getMessage());
        }

        return array_unique($emails);
    }

    private function showSimulatedResults($simulatedUsers, $baremetricsEmails, $commonCount, $missingCount)
    {
        $this->line("📊 RESUMEN SIMULADO DE LA COMPARACIÓN");
        $this->line("=====================================");
        $this->line("👥 Total usuarios GHL (simulados): " . count($simulatedUsers));
        $this->line("👥 Total emails Baremetrics: " . count($baremetricsEmails));
        $this->line("✅ Usuarios en AMBOS sistemas (estimado): {$commonCount}");
        $this->line("❌ Usuarios GHL faltantes en Baremetrics (estimado): {$missingCount}");
        $this->line('');
        
        $totalGHL = count($simulatedUsers);
        $syncPercentage = round(($commonCount / $totalGHL) * 100, 1);
        $missingPercentage = round(($missingCount / $totalGHL) * 100, 1);
        
        $this->line("📈 PORCENTAJES ESTIMADOS:");
        $this->line("   • Sincronizados: {$syncPercentage}%");
        $this->line("   • Faltantes: {$missingPercentage}%");
        $this->line('');

        $this->line("📊 DISTRIBUCIÓN POR TAGS:");
        $tagCounts = [];
        foreach ($simulatedUsers as $user) {
            foreach ($user['tags'] as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        
        foreach ($tagCounts as $tag => $count) {
            $percentage = round(($count / $totalGHL) * 100, 1);
            $this->line("   • {$tag}: {$count} usuarios ({$percentage}%)");
        }
        $this->line('');

        $this->line("🎯 IMPACTO ESTIMADO:");
        $this->line("   • Usuarios que necesitan importación: {$missingCount}");
        $this->line("   • Potencial de crecimiento en Baremetrics: {$missingPercentage}%");
        $this->line("   • Usuarios ya sincronizados: {$commonCount}");
    }
}
