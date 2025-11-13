<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WebhookMailService;
use Illuminate\Support\Str;

class TestCancellationAdminEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cancellation-admin-emails {email : Email del usuario que solicita cancelación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el envío de correos a administradores cuando un usuario solicita cancelación';

    protected $webhookMailService;

    public function __construct(WebhookMailService $webhookMailService)
    {
        parent::__construct();
        $this->webhookMailService = $webhookMailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userEmail = $this->argument('email');
        
        $this->info('🧪 Prueba de envío de correos a administradores');
        $this->line("📧 Email del usuario: {$userEmail}");
        $this->newLine();
        
        // Obtener correos de administradores configurados
        $adminEmails = $this->getCancellationNotificationEmails();
        
        if (empty($adminEmails)) {
            $this->error('❌ No hay correos de administrador configurados en CANCELLATION_NOTIFICATION_EMAILS');
            $this->line('   Por favor, configura la variable de entorno CANCELLATION_NOTIFICATION_EMAILS');
            $this->line('   Ejemplo: CANCELLATION_NOTIFICATION_EMAILS=admin1@ejemplo.com,admin2@ejemplo.com');
            return 1;
        }
        
        $this->info('📋 Correos de administradores configurados:');
        foreach ($adminEmails as $index => $adminEmail) {
            $this->line("   " . ($index + 1) . ". {$adminEmail}");
        }
        $this->newLine();
        
        // Generar datos de prueba
        $token = Str::random(64);
        $verificationUrl = url('cancellation/verify/' . $token);
        $subject = 'COPIA ADMIN - Solicitud de cancelación: ' . $userEmail;
        
        $this->info('📦 Datos de prueba:');
        $this->line("   Token: " . substr($token, 0, 20) . "...");
        $this->line("   URL de verificación: {$verificationUrl}");
        $this->newLine();
        
        // Enviar correos a administradores
        $this->info('📤 Enviando correos a administradores...');
        $this->newLine();
        
        $sentCount = 0;
        $failedCount = 0;
        
        foreach ($adminEmails as $adminEmail) {
            try {
                $this->line("   Enviando a: {$adminEmail}...");
                
                $this->webhookMailService->send($adminEmail, $subject, 'emails.cancellation-verification', [
                    'verificationUrl' => $verificationUrl,
                    'email' => $userEmail,
                    'isAdminCopy' => true,
                    'flowType' => 'survey'
                ]);
                
                $this->info("   ✅ Enviado exitosamente a: {$adminEmail}");
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("   ❌ Error al enviar a {$adminEmail}: " . $e->getMessage());
                $failedCount++;
            }
        }
        
        $this->newLine();
        $this->info('📊 Resumen:');
        $this->line("   Total de administradores: " . count($adminEmails));
        $this->line("   ✅ Enviados exitosamente: {$sentCount}");
        $this->line("   ❌ Fallidos: {$failedCount}");
        
        if ($sentCount > 0) {
            $this->newLine();
            $this->info('✅ Prueba completada. Los correos a administradores se están enviando correctamente.');
            return 0;
        } else {
            $this->newLine();
            $this->error('❌ No se pudo enviar ningún correo a los administradores.');
            $this->line('   Revisa los logs para más detalles.');
            return 1;
        }
    }
    
    /**
     * Obtiene los correos electrónicos configurados para notificaciones de cancelación
     */
    private function getCancellationNotificationEmails()
    {
        $emailsString = config('mail.cancellation_notification_emails', '');
        
        if (empty($emailsString)) {
            $emailsString = env('CANCELLATION_NOTIFICATION_EMAILS', '');
        }
        
        if (empty($emailsString)) {
            return [];
        }
        
        $emails = array_map('trim', explode(',', $emailsString));
        $validEmails = array_filter($emails, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        return array_values(array_unique($validEmails));
    }
}

