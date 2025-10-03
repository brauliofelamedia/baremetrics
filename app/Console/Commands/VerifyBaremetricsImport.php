<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class VerifyBaremetricsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baremetrics:verify-import 
                           {--plan-oid= : Plan OID to verify}
                           {--customer-oid= : Customer OID to verify}
                           {--subscription-oid= : Subscription OID to verify}
                           {--show-all : Show all recent imports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify Baremetrics import status and show imported resources';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 VERIFICANDO IMPORTACIÓN EN BAREMETRICS');
        $this->info('=======================================');

        $environment = config('services.baremetrics.environment');
        $this->info("🌍 Entorno: {$environment}");

        if ($this->option('show-all')) {
            $this->showRecentImports();
            return 0;
        }

        if ($planOid = $this->option('plan-oid')) {
            $this->verifyPlan($planOid);
        }

        if ($customerOid = $this->option('customer-oid')) {
            $this->verifyCustomer($customerOid);
        }

        if ($subscriptionOid = $this->option('subscription-oid')) {
            $this->verifySubscription($subscriptionOid);
        }

        if (!$this->option('plan-oid') && !$this->option('customer-oid') && !$this->option('subscription-oid')) {
            $this->showRecentImports();
        }

        return 0;
    }

    /**
     * Mostrar importaciones recientes desde los logs
     */
    private function showRecentImports()
    {
        $this->info('📋 IMPORTACIONES RECIENTES');
        $this->info('==========================');

        // Leer logs recientes
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) {
            $this->error('❌ No se encontró el archivo de logs');
            return;
        }

        // Buscar líneas de importación recientes
        $recentLogs = $this->getRecentImportLogs($logFile);
        
        if (empty($recentLogs)) {
            $this->warn('⚠️  No se encontraron importaciones recientes');
            return;
        }

        $this->showLogSummary($recentLogs);
    }

    /**
     * Obtener logs recientes de importación
     */
    private function getRecentImportLogs(string $logFile): array
    {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $recentLogs = [];
        
        // Buscar las últimas 100 líneas que contengan "Baremetrics.*Created Successfully"
        $relevantLines = array_filter($lines, function($line) {
            return strpos($line, 'Baremetrics') !== false && 
                   strpos($line, 'Created Successfully') !== false;
        });
        
        return array_slice($relevantLines, -20); // Últimas 20 líneas relevantes
    }

    /**
     * Mostrar resumen de logs
     */
    private function showLogSummary(array $logs): void
    {
        $this->newLine();
        $this->info("📊 RESUMEN DE IMPORTACIONES");
        $this->info("============================");
        
        $plans = 0;
        $customers = 0;
        $subscriptions = 0;
        
        foreach ($logs as $log) {
            if (strpos($log, 'Plan Created Successfully') !== false) {
                $plans++;
            } elseif (strpos($log, 'Customer Created Successfully') !== false) {
                $customers++;
            } elseif (strpos($log, 'Subscription Created Successfully') !== false) {
                $subscriptions++;
            }
        }
        
        $this->line("• Planes creados: {$plans}");
        $this->line("• Clientes creados: {$customers}");
        $this->line("• Suscripciones creadas: {$subscriptions}");
        
        $this->newLine();
        $this->info("📋 IMPORTACIONES RECIENTES");
        $this->info("==========================");
        
        foreach (array_slice($logs, -10) as $log) {
            if (preg_match('/\[([^\]]+)\].*Baremetrics ([^C]+) Created Successfully/', $log, $matches)) {
                $timestamp = $matches[1];
                $type = trim($matches[2]);
                
                if (preg_match('/"oid":"([^"]+)"/', $log, $oidMatches)) {
                    $oid = $oidMatches[1];
                    $this->line("• {$type}: {$oid} ({$timestamp})");
                }
            }
        }
    }

    /**
     * Parsear logs de importación (método original - no usado)
     */
    private function parseImportLogs(string $logs): array
    {
        $imports = [];
        $lines = explode("\n", $logs);

        foreach ($lines as $line) {
            if (strpos($line, 'Baremetrics') !== false && strpos($line, 'Created Successfully') !== false) {
                if (preg_match('/"type":"([^"]+)"/', $line, $matches)) {
                    $type = $matches[1];
                } else {
                    continue;
                }

                if (preg_match('/"oid":"([^"]+)"/', $line, $matches)) {
                    $oid = $matches[1];
                } else {
                    continue;
                }

                if (preg_match('/"created":(\d+)/', $line, $matches)) {
                    $created = (int)$matches[1];
                } else {
                    continue;
                }

                $import = [
                    'type' => $type,
                    'oid' => $oid,
                    'created' => $created
                ];

                // Extraer información específica por tipo
                if ($type === 'plan') {
                    if (preg_match('/"name":"([^"]+)"/', $line, $matches)) {
                        $import['name'] = $matches[1];
                    }
                    if (preg_match('/"amount":(\d+)/', $line, $matches)) {
                        $import['amount'] = (int)$matches[1];
                    }
                }

                if ($type === 'customer') {
                    if (preg_match('/"name":"([^"]+)"/', $line, $matches)) {
                        $import['name'] = $matches[1];
                    }
                    if (preg_match('/"email":"([^"]+)"/', $line, $matches)) {
                        $import['email'] = $matches[1];
                    }
                    if (preg_match('/"plan_oid":"([^"]+)"/', $line, $matches)) {
                        $import['plan_oid'] = $matches[1];
                    }
                }

                if ($type === 'subscription') {
                    if (preg_match('/"plan_oid":"([^"]+)"/', $line, $matches)) {
                        $import['plan_oid'] = $matches[1];
                    }
                    if (preg_match('/"customer_oid":"([^"]+)"/', $line, $matches)) {
                        $import['customer_oid'] = $matches[1];
                    }
                }

                $imports[] = $import;
            }
        }

        // Ordenar por fecha de creación (más reciente primero)
        usort($imports, function($a, $b) {
            return $b['created'] - $a['created'];
        });

        return $imports;
    }

    /**
     * Verificar plan específico
     */
    private function verifyPlan(string $planOid)
    {
        $this->info("📋 Verificando plan: {$planOid}");
        
        // Buscar en logs
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            if (strpos($logs, $planOid) !== false) {
                $this->info("✅ Plan encontrado en logs");
            } else {
                $this->warn("⚠️  Plan no encontrado en logs");
            }
        }
    }

    /**
     * Verificar cliente específico
     */
    private function verifyCustomer(string $customerOid)
    {
        $this->info("👤 Verificando cliente: {$customerOid}");
        
        // Buscar en logs
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            if (strpos($logs, $customerOid) !== false) {
                $this->info("✅ Cliente encontrado en logs");
            } else {
                $this->warn("⚠️  Cliente no encontrado en logs");
            }
        }
    }

    /**
     * Verificar suscripción específica
     */
    private function verifySubscription(string $subscriptionOid)
    {
        $this->info("🔄 Verificando suscripción: {$subscriptionOid}");
        
        // Buscar en logs
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            if (strpos($logs, $subscriptionOid) !== false) {
                $this->info("✅ Suscripción encontrada en logs");
            } else {
                $this->warn("⚠️  Suscripción no encontrada en logs");
            }
        }
    }
}
