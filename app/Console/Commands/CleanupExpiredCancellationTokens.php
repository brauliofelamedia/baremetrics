<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CancellationToken;

class CleanupExpiredCancellationTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancellation:cleanup-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia tokens de cancelación expirados de la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Iniciando limpieza de tokens de cancelación expirados...');
        
        try {
            // Contar tokens expirados antes de la limpieza
            $expiredCount = CancellationToken::expired()->count();
            
            if ($expiredCount === 0) {
                $this->info('✅ No hay tokens expirados para limpiar.');
                return 0;
            }
            
            $this->info("📊 Encontrados {$expiredCount} tokens expirados.");
            
            // Eliminar tokens expirados
            $deletedCount = CancellationToken::cleanupExpiredTokens();
            
            $this->info("🗑️  Eliminados {$deletedCount} tokens expirados.");
            $this->info('✅ Limpieza completada exitosamente.');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error durante la limpieza: ' . $e->getMessage());
            return 1;
        }
    }
}