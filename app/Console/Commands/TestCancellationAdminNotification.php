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
        
        // Almacenamos el token en la base de datos con una duración de 15 minutos
        $tokenRecord = CancellationToken::create([
            'token' => $token,
            'email' => $email,
            'expires_at' => Carbon::now()->addMinutes(15)
        ]);
        
        // También almacenamos en caché para compatibilidad
        Cache::put('cancellation_token_' . $token, $email, Carbon::now()->addMinutes(15));
        
        // Generamos una URL de verificación de prueba
        $verificationUrl = url('cancellation/verify/' . $token);
        
        $this->info("🔑 Token generado: " . substr($token, 0, 20) . "...");
        $this->info("🔗 URL de verificación: {$verificationUrl}");
        
        try {
            // Enviar correo al usuario
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email
            ], function($message) use ($email) {
                $message->to($email)
                    ->subject('Verificación de cancelación de suscripción - PRUEBA');
            });
            
            $this->info("✅ Correo enviado al usuario: {$email}");
            
            // Enviar copia al administrador
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email,
                'isAdminCopy' => true
            ], function($message) use ($email) {
                $message->to('braulio@felamedia.com')
                    ->subject('COPIA ADMIN - Solicitud de cancelación: ' . $email . ' - PRUEBA');
            });
            
            $this->info("✅ Copia administrativa enviada a: braulio@felamedia.com");
            
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
}
