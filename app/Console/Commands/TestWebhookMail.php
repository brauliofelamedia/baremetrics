<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebhookMailService;
use Illuminate\Support\Facades\View;

class TestWebhookMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-webhook 
                           {email : Dirección de correo para enviar la prueba}
                           {--dry-run : Solo mostrar el JSON sin enviarlo}
                           {--template= : Plantilla a usar (cancellation-verification, cancellation-summary, ghl-processing-report)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el envío de correos vía webhook y muestra el JSON que se enviará';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $dryRun = $this->option('dry-run');
        $template = $this->option('template') ?? 'cancellation-verification';
        
        $webhookUrl = env('WEBHOOK_MAIL_URL');
        
        if (empty($webhookUrl)) {
            $this->error('❌ WEBHOOK_MAIL_URL no está configurado en el archivo .env');
            return 1;
        }
        
        $this->info('🔧 Configuración del Webhook:');
        $this->line("   URL: {$webhookUrl}");
        $this->line("   Email destino: {$email}");
        $this->line("   Plantilla: {$template}");
        $this->line("   Modo: " . ($dryRun ? 'DRY-RUN (solo mostrar JSON)' : 'ENVÍO REAL'));
        $this->newLine();
        
        // Preparar datos según la plantilla
        $subject = '';
        $view = '';
        $data = [];
        
        switch ($template) {
            case 'cancellation-verification':
                $subject = 'Prueba - Verificación de cancelación de suscripción';
                $view = 'emails.cancellation-verification';
                $data = [
                    'verificationUrl' => url('cancellation/verify/test-token-123'),
                    'email' => $email,
                    'flowType' => 'survey'
                ];
                break;
                
            case 'cancellation-summary':
                // Para esta plantilla necesitamos un objeto CancellationTracking
                // Vamos a crear datos de prueba
                $subject = 'Prueba - Resumen de Cancelación';
                $view = 'emails.cancellation-summary';
                $data = [
                    'tracking' => (object)[
                        'email' => $email,
                        'customer_id' => 'test-customer-123',
                        'stripe_customer_id' => 'cus_test123',
                        'email_requested' => true,
                        'email_requested_at' => now(),
                        'survey_viewed' => true,
                        'survey_viewed_at' => now(),
                        'survey_completed' => true,
                        'survey_completed_at' => now(),
                        'baremetrics_cancelled' => false,
                        'baremetrics_cancelled_at' => null,
                        'stripe_cancelled' => false,
                        'stripe_cancelled_at' => null,
                        'process_completed' => false,
                        'current_step' => 'survey_completed',
                        'notes' => 'Esta es una prueba del sistema de correos'
                    ],
                    'status' => 'survey_completed',
                    'triggerEvent' => 'test'
                ];
                break;
                
            case 'ghl-processing-report':
                $subject = 'Prueba - Reporte de Procesamiento GHL';
                $view = 'emails.ghl-processing-report';
                $data = [
                    'stats' => [
                        'total_processed' => 100,
                        'successful_updates' => 95,
                        'failed_updates' => 5,
                        'duration' => 10.5,
                        'start_time' => now()->subMinutes(10),
                        'end_time' => now(),
                        'is_dry_run' => false,
                        'errors' => []
                    ],
                    'subject' => $subject,
                    'is_dry_run' => false
                ];
                break;
                
            default:
                $this->error("❌ Plantilla '{$template}' no reconocida.");
                $this->line("Plantillas disponibles: cancellation-verification, cancellation-summary, ghl-processing-report");
                return 1;
        }
        
        try {
            // Renderizar la plantilla a HTML
            $html = View::make($view, $data)->render();
            
            // Preparar el payload
            $payload = [
                'email' => $email,
                'subject' => $subject,
                'html' => $html
            ];
            
            $this->info('📦 Payload JSON que se enviará al webhook:');
            $this->newLine();
            $this->line('═══════════════════════════════════════════════════════════');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line('═══════════════════════════════════════════════════════════');
            $this->newLine();
            
            // Mostrar estadísticas
            $htmlSize = strlen($html);
            $jsonSize = strlen(json_encode($payload));
            
            $this->info('📊 Estadísticas:');
            $this->line("   Tamaño del HTML: " . number_format($htmlSize) . " bytes (" . round($htmlSize / 1024, 2) . " KB)");
            $this->line("   Tamaño del JSON: " . number_format($jsonSize) . " bytes (" . round($jsonSize / 1024, 2) . " KB)");
            $this->newLine();
            
            if ($dryRun) {
                $this->warn('⚠️  MODO DRY-RUN: No se envió el correo. Para enviarlo, ejecuta sin --dry-run');
                return 0;
            }
            
            // Enviar el correo
            $this->info('📤 Enviando correo al webhook...');
            $webhookMailService = app(WebhookMailService::class);
            
            $result = $webhookMailService->send($email, $subject, $view, $data);
            
            if ($result) {
                $this->info('✅ Correo enviado exitosamente al webhook!');
                $this->line('   Revisa los logs para más detalles.');
            } else {
                $this->error('❌ Error al enviar el correo al webhook');
                $this->line('   Revisa los logs para ver el error específico.');
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}

