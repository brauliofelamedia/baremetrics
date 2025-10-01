<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class TestGHLSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:test-subscriptions 
                           {email : Email del usuario a buscar}
                           {--debug : Mostrar información de debugging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la obtención de suscripciones de GoHighLevel para un usuario específico';

    protected $ghlService;

    public function __construct(GoHighLevelService $ghlService)
    {
        parent::__construct();
        $this->ghlService = $ghlService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $debug = $this->option('debug');

        $this->info("🧪 Probando obtención de suscripciones para: {$email}");
        $this->newLine();

        try {
            // Buscar usuario en GoHighLevel
            $this->info('🔍 Buscando usuario en GoHighLevel...');
            $ghlCustomer = $this->ghlService->getContactsByExactEmail($email);
            
            if (empty($ghlCustomer['contacts'])) {
                $this->info('  🔍 Intentando búsqueda con "contains"...');
                $ghlCustomer = $this->ghlService->getContacts($email);
            }

            if (empty($ghlCustomer['contacts'])) {
                $this->error("❌ Usuario no encontrado en GoHighLevel: {$email}");
                return 1;
            }

            $contact = $ghlCustomer['contacts'][0];
            $contactId = $contact['id'];
            
            $this->info("✅ Usuario encontrado (ID: {$contactId})");

            // Probar obtención de suscripciones
            $this->info('📋 Obteniendo suscripciones...');
            
            try {
                $subscription = $this->ghlService->getSubscriptionStatusByContact($contactId);
                
                if ($subscription) {
                    $this->info('✅ Suscripción obtenida exitosamente');
                    
                    // Mostrar detalles de la suscripción
                    $this->displaySubscriptionDetails($subscription, $debug);
                    
                } else {
                    $this->warn('⚠️  No se encontraron suscripciones para este usuario');
                }

            } catch (\Exception $e) {
                $this->error('❌ Error obteniendo suscripción: ' . $e->getMessage());
                
                if ($debug) {
                    $this->line('Stack trace:');
                    $this->line($e->getTraceAsString());
                }
            }

            // Probar método alternativo
            $this->newLine();
            $this->info('🔄 Probando método alternativo (suscripción más reciente)...');
            
            try {
                $latestSubscription = $this->ghlService->getMostRecentActiveSubscription($contactId);
                
                if ($latestSubscription) {
                    $this->info('✅ Suscripción más reciente obtenida exitosamente');
                    $this->displaySubscriptionDetails($latestSubscription, $debug, 'MÁS RECIENTE');
                } else {
                    $this->warn('⚠️  No se encontraron suscripciones');
                }

            } catch (\Exception $e) {
                $this->error('❌ Error obteniendo suscripción más reciente: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Muestra los detalles de una suscripción
     */
    private function displaySubscriptionDetails($subscription, $debug = false, $type = 'MÁS RECIENTE')
    {
        $this->newLine();
        $this->info("📊 DETALLES DE SUSCRIPCIÓN ({$type}):");
        $this->info('=====================================');
        
        $details = [
            'ID de Suscripción' => $subscription['id'] ?? 'N/A',
            'Estado' => $subscription['status'] ?? 'N/A',
            'Código de Cupón' => $subscription['couponCode'] ?? 'N/A',
            'Fecha de Creación' => $subscription['createdAt'] ?? 'N/A',
            'Fecha de Actualización' => $subscription['updatedAt'] ?? 'N/A',
            'Fecha de Inicio' => $subscription['startDate'] ?? 'N/A',
            'Fecha de Fin' => $subscription['endDate'] ?? 'N/A',
            'Precio' => $subscription['price'] ?? 'N/A',
            'Moneda' => $subscription['currency'] ?? 'N/A',
            'Frecuencia' => $subscription['frequency'] ?? 'N/A',
        ];

        $this->table(['Campo', 'Valor'], array_map(function($key, $value) {
            return [$key, $value];
        }, array_keys($details), $details));

        if ($debug) {
            $this->newLine();
            $this->info('🔍 DATOS COMPLETOS (DEBUG):');
            $this->line(json_encode($subscription, JSON_PRETTY_PRINT));
        }

        // Análisis del estado
        $this->newLine();
        $this->info('📈 ANÁLISIS:');
        
        $status = strtolower($subscription['status'] ?? '');
        if (in_array($status, ['active', 'trialing', 'past_due'])) {
            $this->info('✅ Estado: ACTIVO');
        } elseif (in_array($status, ['cancelled', 'canceled', 'expired'])) {
            $this->warn('⚠️  Estado: CANCELADO/EXPIRADO');
        } else {
            $this->line("ℹ️  Estado: {$subscription['status']}");
        }

        if (!empty($subscription['couponCode'])) {
            $this->info("🎫 Cupón utilizado: {$subscription['couponCode']}");
        } else {
            $this->line('🎫 Sin cupón utilizado');
        }
    }
}
