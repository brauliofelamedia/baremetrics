<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AnalyzeGHLComparisonResults extends Command
{
    protected $signature = 'ghl:analyze-results {--file=storage/csv/ghl_baremetrics_comparison_2025-10-03_17-50-03.json}';
    protected $description = 'Analyze the results from the GHL CSV comparison';

    public function handle()
    {
        $jsonFile = $this->option('file');
        
        if (!file_exists($jsonFile)) {
            $this->error("❌ El archivo JSON no existe: {$jsonFile}");
            return;
        }

        $this->info("🔍 Analizando resultados de la comparación GHL vs Baremetrics...");
        $this->line('');

        $data = json_decode(file_get_contents($jsonFile), true);
        
        if (!$data) {
            $this->error("❌ Error al leer el archivo JSON");
            return;
        }

        $summary = $data['summary'];
        $missingUsers = $data['missing_users'];

        // Mostrar resumen principal
        $this->showMainSummary($summary);

        // Analizar distribución por tags
        $this->analyzeTagDistribution($missingUsers);

        // Analizar por fechas de creación
        $this->analyzeByCreationDate($missingUsers);

        // Mostrar recomendaciones
        $this->showRecommendations($summary);
    }

    private function showMainSummary($summary)
    {
        $this->line("📊 RESUMEN PRINCIPAL");
        $this->line("===================");
        $this->line("👥 Total usuarios GHL procesados: " . number_format($summary['total_ghl_users']));
        $this->line("👥 Total emails en Baremetrics: " . number_format($summary['total_baremetrics_emails']));
        $this->line("✅ Usuarios ya sincronizados: " . number_format($summary['common_users']));
        $this->line("❌ Usuarios faltantes en Baremetrics: " . number_format($summary['missing_users']));
        $this->line('');
        
        $this->line("📈 PORCENTAJES:");
        $this->line("   • Sincronizados: {$summary['sync_percentage']}%");
        $this->line("   • Faltantes: {$summary['missing_percentage']}%");
        $this->line('');
    }

    private function analyzeTagDistribution($missingUsers)
    {
        $this->line("🏷️ DISTRIBUCIÓN POR TAGS");
        $this->line("========================");
        
        $tagCounts = [];
        $totalUsers = count($missingUsers);
        
        foreach ($missingUsers as $user) {
            $tags = $this->parseTags($user['tags']);
            foreach ($tags as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        
        // Ordenar por frecuencia
        arsort($tagCounts);
        
        $count = 0;
        foreach ($tagCounts as $tag => $count) {
            if ($count >= 10) { // Solo mostrar tags con 10+ usuarios
                $percentage = round(($count / $totalUsers) * 100, 1);
                $this->line("   • {$tag}: " . number_format($count) . " usuarios ({$percentage}%)");
                $count++;
                if ($count >= 20) break; // Limitar a 20 tags principales
            }
        }
        $this->line('');
    }

    private function analyzeByCreationDate($missingUsers)
    {
        $this->line("📅 ANÁLISIS POR FECHA DE CREACIÓN");
        $this->line("=================================");
        
        $monthCounts = [];
        $yearCounts = [];
        
        foreach ($missingUsers as $user) {
            if (!empty($user['created'])) {
                try {
                    $date = new \DateTime($user['created']);
                    $month = $date->format('Y-m');
                    $year = $date->format('Y');
                    
                    $monthCounts[$month] = ($monthCounts[$month] ?? 0) + 1;
                    $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
                } catch (\Exception $e) {
                    // Ignorar fechas inválidas
                }
            }
        }
        
        // Mostrar últimos 12 meses
        krsort($monthCounts);
        $count = 0;
        foreach ($monthCounts as $month => $count) {
            if ($count >= 0) {
                $this->line("   • {$month}: " . number_format($count) . " usuarios");
                $count++;
                if ($count >= 12) break;
            }
        }
        $this->line('');
    }

    private function showRecommendations($summary)
    {
        $this->line("💡 RECOMENDACIONES");
        $this->line("==================");
        
        $missingCount = $summary['missing_users'];
        $missingPercentage = $summary['missing_percentage'];
        
        if ($missingPercentage > 90) {
            $this->line("🚨 ALTA PRIORIDAD: {$missingPercentage}% de usuarios faltantes");
            $this->line("   • Implementar importación masiva urgente");
            $this->line("   • Configurar sincronización automática");
            $this->line("   • Revisar proceso de onboarding");
        } elseif ($missingPercentage > 50) {
            $this->line("⚠️ PRIORIDAD MEDIA: {$missingPercentage}% de usuarios faltantes");
            $this->line("   • Planificar importación por lotes");
            $this->line("   • Identificar usuarios de mayor valor");
        } else {
            $this->line("✅ BUEN ESTADO: {$missingPercentage}% de usuarios faltantes");
            $this->line("   • Mantener sincronización regular");
            $this->line("   • Monitorear nuevos usuarios");
        }
        
        $this->line('');
        $this->line("📋 PRÓXIMOS PASOS:");
        $this->line("   1. Revisar archivo CSV de usuarios faltantes");
        $this->line("   2. Configurar importación en Baremetrics");
        $this->line("   3. Establecer proceso de sincronización regular");
        $this->line("   4. Monitorear métricas de crecimiento");
        $this->line('');
        
        $this->line("📁 ARCHIVOS GENERADOS:");
        $this->line("   • JSON completo: Para análisis detallado");
        $this->line("   • CSV usuarios faltantes: Para importación masiva");
    }

    private function parseTags($tagsString)
    {
        if (empty($tagsString)) {
            return [];
        }

        $tags = preg_split('/[,;|]+/', $tagsString);
        return array_map('trim', array_filter($tags));
    }
}
