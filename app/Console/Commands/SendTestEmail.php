<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email : The email address to send the test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify mail configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Sending test email to: {$email}");
        
        try {
            $webhookMailService = app(\App\Services\WebhookMailService::class);
            $html = '<html><body><p>This is a test email from your Laravel application.</p></body></html>';
            $webhookMailService->sendRaw($email, 'Test Email from Laravel App', $html);
            
            $this->info('Test email sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send test email.');
            $this->error('Error: ' . $e->getMessage());
        }
        
        return 0;
    }
}
