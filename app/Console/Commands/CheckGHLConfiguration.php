<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoHighLevelService;
use App\Services\BaremetricsService;
use App\Services\StripeService;

class CheckGHLConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ghl:check-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la configuración de GoHighLevel, Baremetrics y Stripe';

    protected $ghlService;
    protected $baremetricsService;
    protected $stripeService;

    public function __construct(
        GoHighLevelService $ghlService,
        BaremetricsService $baremetricsService,
        StripeService $stripeService
    ) {
        parent::__construct();
        $this->ghlService = $ghlService;
        $this->baremetricsService = $baremetricsService;
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Verificando configuración del sistema...');
        $this->newLine();

        $allGood = true;

        // Verificar configuración de GoHighLevel
        $allGood &= $this->checkGoHighLevelConfig();

        // Verificar configuración de Baremetrics
        $allGood &= $this->checkBaremetricsConfig();

        // Verificar configuración de Stripe
        $allGood &= $this->checkStripeConfig();

        // Verificar configuración de correo
        $allGood &= $this->checkMailConfig();

        $this->newLine();
        
        if ($allGood) {
            $this->info('✅ ¡Toda la configuración está correcta!');
            $this->info('🚀 El sistema está listo para procesar usuarios.');
        } else {
            $this->error('❌ Hay problemas en la configuración.');
            $this->warn('⚠️  Corrige los errores antes de ejecutar el procesamiento.');
        }

        return $allGood ? 0 : 1;
    }

    /**
     * Verifica la configuración de GoHighLevel
     */
    private function checkGoHighLevelConfig()
    {
        $this->info('📡 Verificando GoHighLevel...');
        
        $checks = [
            'GHL_CLIENT_ID' => config('services.gohighlevel.client_id'),
            'GHL_CLIENT_SECRET' => config('services.gohighlevel.client_secret'),
            'GHL_LOCATION' => config('services.gohighlevel.location'),
            'GHL_NOTIFICATION_EMAIL' => config('services.gohighlevel.notification_email'),
        ];

        $allGood = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$key}: No configurado");
                $allGood = false;
            } else {
                $this->info("  ✅ {$key}: Configurado");
            }
        }

        // Probar conexión con GHL
        try {
            $this->info('  🔍 Probando conexión con GoHighLevel...');
            $response = $this->ghlService->getLocation();
            if ($response) {
                $this->info('  ✅ Conexión con GoHighLevel: OK');
                
                // Verificar datos de ubicación
                $locationData = json_decode($response, true);
                if ($locationData && isset($locationData['locations'])) {
                    $this->info('  📍 Ubicaciones disponibles: ' . count($locationData['locations']));
                }
            } else {
                $this->error('  ❌ Conexión con GoHighLevel: FALLO');
                $allGood = false;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Error de conexión con GoHighLevel: ' . $e->getMessage());
            
            // Analizar el tipo de error
            if (strpos($e->getMessage(), '401') !== false) {
                $this->warn('  💡 Error 401: Token inválido o expirado. Ejecuta: php artisan ghl:diagnose-connection');
            } elseif (strpos($e->getMessage(), '403') !== false) {
                $this->warn('  💡 Error 403: Permisos insuficientes');
            }
            
            $allGood = false;
        }

        return $allGood;
    }

    /**
     * Verifica la configuración de Baremetrics
     */
    private function checkBaremetricsConfig()
    {
        $this->info('📊 Verificando Baremetrics...');
        
        $environment = config('services.baremetrics.environment');
        $this->info("  📋 Entorno: {$environment}");

        $checks = [];
        if ($environment === 'production') {
            $checks['BAREMETRICS_LIVE_KEY'] = config('services.baremetrics.live_key');
        } else {
            $checks['BAREMETRICS_SANDBOX_KEY'] = config('services.baremetrics.sandbox_key');
        }

        $allGood = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$key}: No configurado");
                $allGood = false;
            } else {
                $this->info("  ✅ {$key}: Configurado");
            }
        }

        // Probar conexión con Baremetrics
        try {
            $this->info('  🔍 Probando conexión con Baremetrics...');
            $response = $this->baremetricsService->getAccount();
            if ($response) {
                $this->info('  ✅ Conexión con Baremetrics: OK');
                
                // Verificar fuentes disponibles
                $sources = $this->baremetricsService->getSources();
                if ($sources) {
                    $stripeSources = 0;
                    if (isset($sources['sources'])) {
                        foreach ($sources['sources'] as $source) {
                            if (isset($source['provider']) && $source['provider'] === 'stripe') {
                                $stripeSources++;
                            }
                        }
                    }
                    $this->info("  📈 Fuentes de Stripe encontradas: {$stripeSources}");
                }
            } else {
                $this->error('  ❌ Conexión con Baremetrics: FALLO');
                $allGood = false;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Error de conexión con Baremetrics: ' . $e->getMessage());
            $allGood = false;
        }

        return $allGood;
    }

    /**
     * Verifica la configuración de Stripe
     */
    private function checkStripeConfig()
    {
        $this->info('💳 Verificando Stripe...');
        
        $checks = [
            'STRIPE_PUBLISHABLE_KEY' => config('services.stripe.publishable_key'),
            'STRIPE_SECRET_KEY' => config('services.stripe.secret_key'),
        ];

        $allGood = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$key}: No configurado");
                $allGood = false;
            } else {
                $this->info("  ✅ {$key}: Configurado");
            }
        }

        // Probar conexión con Stripe
        try {
            $this->info('  🔍 Probando conexión con Stripe...');
            $response = $this->stripeService->getCustomerIds(1);
            if ($response && $response['success']) {
                $this->info('  ✅ Conexión con Stripe: OK');
            } else {
                $this->error('  ❌ Conexión con Stripe: FALLO');
                $allGood = false;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Error de conexión con Stripe: ' . $e->getMessage());
            $allGood = false;
        }

        return $allGood;
    }

    /**
     * Verifica la configuración de correo
     */
    private function checkMailConfig()
    {
        $this->info('📧 Verificando configuración de correo...');
        
        $checks = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            'MAIL_FROM_NAME' => config('mail.from.name'),
        ];

        $allGood = true;
        foreach ($checks as $key => $value) {
            if (empty($value)) {
                $this->error("  ❌ {$key}: No configurado");
                $allGood = false;
            } else {
                $this->info("  ✅ {$key}: Configurado");
            }
        }

        // Verificar si hay correo de notificación configurado
        $notificationEmail = config('services.gohighlevel.notification_email');
        if (empty($notificationEmail)) {
            $this->warn('  ⚠️  GHL_NOTIFICATION_EMAIL: No configurado (opcional)');
        } else {
            $this->info("  ✅ GHL_NOTIFICATION_EMAIL: {$notificationEmail}");
        }

        return $allGood;
    }
}
