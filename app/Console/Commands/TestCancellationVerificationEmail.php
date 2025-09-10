<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TestCancellationVerificationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:cancellation-verification {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un correo de prueba de verificación de cancelación a una dirección especificada';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Enviando correo de verificación de cancelación a: {$email}");
        
        // Generamos un token de prueba
        $token = Str::random(64);
        
        // Generamos una URL de verificación de prueba
        $verificationUrl = url('cancellation/verify/' . $token);
        
        try {
            Mail::send('emails.cancellation-verification', [
                'verificationUrl' => $verificationUrl,
                'email' => $email
            ], function($message) use ($email) {
                $message->to($email)
                    ->subject('Verificación de cancelación de suscripción - Prueba');
            });
            
            $this->info("✓ Correo de verificación de cancelación enviado correctamente a {$email}");
            $this->info("✓ URL de verificación (prueba): {$verificationUrl}");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error al enviar el correo de verificación de cancelación: " . $e->getMessage());
            return 1;
        }
    }
}
