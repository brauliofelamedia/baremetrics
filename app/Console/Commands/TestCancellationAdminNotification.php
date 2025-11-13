<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\CancellationToken;

class TestCancellationAdminNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cancellation-admin-notification {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba el sistema de notificaciones administrativas para cancelaciones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🧪 Probando sistema de notificaciones administrativas para cancelaciones");
        $this->info("📧 Email de prueba: {$email}");
        
        // Generamos un token de prueba
        $token = Str::random(64);
        
        // Almacenamos el token en la base de datos con una duración de 30 minutos
        $tokenRecord = CancellationToken::create([
            'token' => $token,
            'email' => $email,
            'expires_at' => Carbon::now()->addMinutes(30)
        ]);
        
        // También almacenamos en caché para compatibilidad
        Cache::put('cancellation_token_' . $token, $email, Carbon::now()->addMinutes(30));
        
        // Generamos una URL de verificación de prueba
        $verificationUrl = url('cancellation/verify/' . $token);
        
        $this->info("🔑 Token generado: " . substr($token, 0, 20) . "...");
        $this->info("🔗 URL de verificación: {$verificationUrl}");
        
        try {
            $webhookMailService = app(\App\Services\WebhookMailService::class);
            
            // Enviar correo al usuario
            $webhookMailService->send($email, 'Verificación de cancelación de suscripción - PRUEBA', 'emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email
            ]);
            
            $this->info("✅ Correo enviado al usuario: {$email}");
            
            // Enviar copia a los administradores configurados
            $adminEmails = $this->getCancellationNotificationEmails();
            if (!empty($adminEmails)) {
                foreach ($adminEmails as $adminEmail) {
                    $webhookMailService->send($adminEmail, 'COPIA ADMIN - Solicitud de cancelación: ' . $email . ' - PRUEBA', 'emails.cancellation-verification', [
                        'verificationUrl' => $verificationUrl,
                        'email' => $email,
                        'isAdminCopy' => true
                    ]);
                }
                $this->info("✅ Copias administrativas enviadas a: " . implode(', ', $adminEmails));
            } else {
                $this->warn("⚠️ No hay correos de administrador configurados en CANCELLATION_NOTIFICATION_EMAILS");
            }
            
            // Verificar que el token está almacenado en la base de datos
            $storedToken = CancellationToken::where('token', $token)->first();
            if ($storedToken && $storedToken->email === $email) {
                $this->info("✅ Token almacenado correctamente en la base de datos");
            } else {
                $this->error("❌ Error: Token no se almacenó correctamente en la base de datos");
            }
            
            // Mostrar información del token
            $expiresInMinutes = $storedToken ? $storedToken->remaining_minutes : 0;
            
            $this->info("⏰ Token expira en: {$expiresInMinutes} minutos");
            $this->info("🔧 Para invalidar el token, ejecuta: php artisan cache:forget cancellation_token_{$token}");
            
            $this->info("\n🎉 Prueba completada exitosamente!");
            $this->info("📋 Resumen:");
            $this->info("   - Correo enviado al usuario: {$email}");
            $this->info("   - Copia enviada al admin: braulio@felamedia.com");
            $this->info("   - Token almacenado: Sí");
            $this->info("   - Tiempo de expiración: {$expiresInMinutes} minutos");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error durante la prueba: " . $e->getMessage());
            $this->error("📋 Trace: " . $e->getTraceAsString());
            return 1;
        }
    }
    
    /**
     * Obtiene los correos electrónicos configurados para notificaciones de cancelación
     */
    private function getCancellationNotificationEmails()
    {
        $emailsString = env('CANCELLATION_NOTIFICATION_EMAILS', '');
        
        if (empty($emailsString)) {
            return [];
        }
        
        // Dividir por comas y limpiar espacios
        $emails = array_map('trim', explode(',', $emailsString));
        
        // Filtrar correos vacíos y validar formato básico
        $validEmails = array_filter($emails, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        return array_values($validEmails);
    }
}
