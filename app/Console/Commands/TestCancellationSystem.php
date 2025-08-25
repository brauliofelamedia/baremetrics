<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StripeService;

class TestCancellationSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancellation:test {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the cancellation system by searching for a customer';

    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('Enter customer email to test');
        
        if (!$email) {
            $this->error('Email is required');
            return 1;
        }

        $this->info("Testing cancellation system with email: {$email}");
        $this->newLine();

        // Test Stripe connection
        $this->info('1. Testing Stripe connection...');
        try {
            $result = $this->stripeService->searchCustomersByEmail($email);
            
            if ($result['success']) {
                $customers = $result['data'] ?? [];
                $this->info("✓ Stripe connection successful");
                $this->info("✓ Found " . count($customers) . " customer(s) with email: {$email}");
                
                if (!empty($customers)) {
                    $this->newLine();
                    $this->info('Customer details:');
                    foreach ($customers as $index => $customer) {
                        $this->line("  {$index}. ID: {$customer['id']}");
                        $this->line("     Name: " . ($customer['name'] ?? 'N/A'));
                        $this->line("     Email: " . ($customer['email'] ?? 'N/A'));
                        $this->line("     Created: " . date('Y-m-d H:i:s', $customer['created']));
                    }
                }
            } else {
                $this->error("✗ Stripe connection failed: " . $result['error']);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("✗ Exception in Stripe connection: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('2. Testing manual cancellation logging...');
        
        // Test manual cancellation logging
        try {
            \Log::info('Manual cancellation test', [
                'customer_email' => $email,
                'test_mode' => true,
                'timestamp' => now(),
                'initiated_by' => 'console_command'
            ]);
            
            $this->info('✓ Manual cancellation logging works');
        } catch (\Exception $e) {
            $this->error("✗ Manual cancellation logging failed: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('3. System recommendations:');
        
        if (!empty($customers)) {
            $this->line('• Customers found - cancellation buttons should be available');
            $this->line('• Test the Baremetrics script loading in browser console');
            $this->line('• If Baremetrics script fails, manual cancellation form should appear');
        } else {
            $this->line('• No customers found - test with a different email');
        }
        
        $this->line('• Check browser console for Baremetrics script loading status');
        $this->line('• Check Laravel logs for manual cancellation requests');
        
        $this->newLine();
        $this->info('✓ Cancellation system test completed');
        
        return 0;
    }
}
