<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CancellationToken;
use Illuminate\Support\Facades\Cache;

class DiagnoseCancellationTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancellation:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica el estado del sistema de tokens de cancelación';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnóstico del Sistema de Tokens de Cancelación');
        $this->info('=' . str_repeat('=', 50));
        
        // 1. Verificar tabla de tokens
        $this->info("\n📊 ESTADO DE LA BASE DE DATOS:");
        $totalTokens = CancellationToken::count();
        $activeTokens = CancellationToken::active()->count();
        $expiredTokens = CancellationToken::expired()->count();
        $usedTokens = CancellationToken::where('is_used', true)->count();
        
        $this->info("   • Total de tokens: {$totalTokens}");
        $this->info("   • Tokens activos: {$activeTokens}");
        $this->info("   • Tokens expirados: {$expiredTokens}");
        $this->info("   • Tokens usados: {$usedTokens}");
        
        // 2. Mostrar tokens activos
        if ($activeTokens > 0) {
            $this->info("\n🔑 TOKENS ACTIVOS:");
            CancellationToken::active()->orderBy('expires_at', 'asc')->get()->each(function ($token) {
                $this->info("   • Email: {$token->email}");
                $this->info("     Token: " . substr($token->token, 0, 20) . "...");
                $this->info("     Expira en: {$token->remaining_minutes} minutos");
                $this->info("     Creado: {$token->created_at}");
                $this->info("     ---");
            });
        }
        
        // 3. Verificar configuración de caché
        $this->info("\n⚙️ CONFIGURACIÓN DE CACHÉ:");
        $cacheDriver = config('cache.default');
        $this->info("   • Driver de caché: {$cacheDriver}");
        
        // 4. Verificar conectividad de caché
        try {
            Cache::put('test_key', 'test_value', 60);
            $testValue = Cache::get('test_key');
            if ($testValue === 'test_value') {
                $this->info("   • Estado de caché: ✅ Funcionando");
                Cache::forget('test_key');
            } else {
                $this->error("   • Estado de caché: ❌ Error");
            }
        } catch (\Exception $e) {
            $this->error("   • Estado de caché: ❌ Error - " . $e->getMessage());
        }
        
        // 5. Verificar logs recientes
        $this->info("\n📝 LOGS RECIENTES:");
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            $cancellationLogs = array_filter(explode("\n", $logs), function($line) {
                return strpos($line, 'Generando token de cancelación') !== false || 
                       strpos($line, 'Token almacenado en base de datos') !== false ||
                       strpos($line, 'Token almacenado en caché') !== false;
            });
            
            $recentLogs = array_slice($cancellationLogs, -5); // Últimos 5 logs
            
            if (count($recentLogs) > 0) {
                $this->info("   • Últimas actividades de tokens:");
                foreach ($recentLogs as $log) {
                    $this->info("     " . substr($log, 0, 100) . "...");
                }
            } else {
                $this->info("   • No hay logs recientes de tokens");
            }
        } else {
            $this->warn("   • Archivo de log no encontrado");
        }
        
        // 6. Recomendaciones
        $this->info("\n💡 RECOMENDACIONES:");
        
        if ($expiredTokens > 10) {
            $this->warn("   • Ejecutar limpieza: php artisan cancellation:cleanup-tokens");
        }
        
        if ($activeTokens === 0) {
            $this->info("   • No hay tokens activos - sistema funcionando correctamente");
        }
        
        if ($cacheDriver !== 'redis' && $cacheDriver !== 'database') {
            $this->warn("   • Considerar usar Redis para mejor rendimiento");
        }
        
        $this->info("\n✅ Diagnóstico completado");
        
        return 0;
    }
}