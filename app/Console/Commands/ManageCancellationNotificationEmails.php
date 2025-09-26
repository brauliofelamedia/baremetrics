<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ManageCancellationNotificationEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancellation:manage-emails {action?} {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestiona los correos electrónicos para notificaciones de cancelación';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action') ?? 'list';
        $email = $this->argument('email');
        
        $this->info('📧 Gestión de Correos de Notificación de Cancelación');
        $this->info('=' . str_repeat('=', 50));
        
        switch ($action) {
            case 'list':
                $this->listEmails();
                break;
            case 'add':
                if (!$email) {
                    $email = $this->ask('Ingrese el correo electrónico a agregar');
                }
                $this->addEmail($email);
                break;
            case 'remove':
                if (!$email) {
                    $email = $this->ask('Ingrese el correo electrónico a remover');
                }
                $this->removeEmail($email);
                break;
            case 'set':
                if (!$email) {
                    $email = $this->ask('Ingrese los correos electrónicos separados por comas');
                }
                $this->setEmails($email);
                break;
            case 'test':
                $this->testEmails();
                break;
            default:
                $this->showHelp();
                break;
        }
        
        return 0;
    }
    
    private function listEmails()
    {
        $emails = $this->getCurrentEmails();
        
        if (empty($emails)) {
            $this->warn('⚠️ No hay correos configurados');
            $this->info('Use: php artisan cancellation:manage-emails add email@ejemplo.com');
        } else {
            $this->info('📋 Correos configurados actualmente:');
            foreach ($emails as $index => $email) {
                $this->info("   " . ($index + 1) . ". {$email}");
            }
        }
        
        $this->info("\n💡 Comandos disponibles:");
        $this->info("   • php artisan cancellation:manage-emails add email@ejemplo.com");
        $this->info("   • php artisan cancellation:manage-emails remove email@ejemplo.com");
        $this->info("   • php artisan cancellation:manage-emails set email1@ejemplo.com,email2@ejemplo.com");
        $this->info("   • php artisan cancellation:manage-emails test");
    }
    
    private function addEmail($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("❌ Correo electrónico inválido: {$email}");
            return;
        }
        
        $emails = $this->getCurrentEmails();
        
        if (in_array($email, $emails)) {
            $this->warn("⚠️ El correo {$email} ya está configurado");
            return;
        }
        
        $emails[] = $email;
        $this->updateEnvFile($emails);
        
        $this->info("✅ Correo {$email} agregado exitosamente");
        $this->info("📧 Total de correos configurados: " . count($emails));
    }
    
    private function removeEmail($email)
    {
        $emails = $this->getCurrentEmails();
        
        if (!in_array($email, $emails)) {
            $this->warn("⚠️ El correo {$email} no está configurado");
            return;
        }
        
        $emails = array_filter($emails, function($e) use ($email) {
            return $e !== $email;
        });
        
        $this->updateEnvFile($emails);
        
        $this->info("✅ Correo {$email} removido exitosamente");
        $this->info("📧 Total de correos configurados: " . count($emails));
    }
    
    private function setEmails($emailsString)
    {
        $emails = array_map('trim', explode(',', $emailsString));
        $validEmails = [];
        
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validEmails[] = $email;
            } else {
                $this->warn("⚠️ Correo inválido ignorado: {$email}");
            }
        }
        
        if (empty($validEmails)) {
            $this->error("❌ No se encontraron correos válidos");
            return;
        }
        
        $this->updateEnvFile($validEmails);
        
        $this->info("✅ Correos configurados exitosamente:");
        foreach ($validEmails as $index => $email) {
            $this->info("   " . ($index + 1) . ". {$email}");
        }
    }
    
    private function testEmails()
    {
        $emails = $this->getCurrentEmails();
        
        if (empty($emails)) {
            $this->warn('⚠️ No hay correos configurados para probar');
            return;
        }
        
        $this->info('🧪 Probando sistema con correos configurados...');
        
        $testEmail = 'test@ejemplo.com';
        $this->call('test:cancellation-admin-notification', ['email' => $testEmail]);
        
        $this->info("\n📧 Correos que deberían haber recibido la notificación:");
        foreach ($emails as $index => $email) {
            $this->info("   " . ($index + 1) . ". {$email}");
        }
    }
    
    private function getCurrentEmails()
    {
        $emailsString = env('CANCELLATION_NOTIFICATION_EMAILS', '');
        
        if (empty($emailsString)) {
            return [];
        }
        
        $emails = array_map('trim', explode(',', $emailsString));
        return array_filter($emails, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
    }
    
    private function updateEnvFile($emails)
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);
        
        $emailsString = implode(',', $emails);
        
        // Buscar la línea existente
        if (preg_match('/^CANCELLATION_NOTIFICATION_EMAILS=.*$/m', $envContent)) {
            // Reemplazar línea existente
            $envContent = preg_replace(
                '/^CANCELLATION_NOTIFICATION_EMAILS=.*$/m',
                "CANCELLATION_NOTIFICATION_EMAILS={$emailsString}",
                $envContent
            );
        } else {
            // Agregar nueva línea
            $envContent .= "\nCANCELLATION_NOTIFICATION_EMAILS={$emailsString}\n";
        }
        
        file_put_contents($envFile, $envContent);
        
        // Limpiar caché de configuración
        $this->call('config:clear');
    }
    
    private function showHelp()
    {
        $this->info('📧 Gestión de Correos de Notificación de Cancelación');
        $this->info('');
        $this->info('Comandos disponibles:');
        $this->info('  list                    - Lista correos configurados');
        $this->info('  add <email>             - Agrega un correo');
        $this->info('  remove <email>          - Remueve un correo');
        $this->info('  set <email1,email2>     - Configura múltiples correos');
        $this->info('  test                    - Prueba el sistema');
        $this->info('');
        $this->info('Ejemplos:');
        $this->info('  php artisan cancellation:manage-emails list');
        $this->info('  php artisan cancellation:manage-emails add admin@ejemplo.com');
        $this->info('  php artisan cancellation:manage-emails set admin@ejemplo.com,soporte@ejemplo.com');
        $this->info('  php artisan cancellation:manage-emails test');
    }
}