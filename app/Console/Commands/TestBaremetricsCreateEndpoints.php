<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;
use Illuminate\Support\Facades\Log;

class TestBaremetricsCreateEndpoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baremetrics:test-create-endpoints 
                           {--test-customer : Test customer creation}
                           {--test-plan : Test plan creation}
                           {--test-subscription : Test subscription creation}
                           {--test-complete : Test complete customer setup}
                           {--test-sources : Test getting sources}
                           {--all : Test all endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the new Baremetrics create endpoints (customer, plan, subscription)';

    protected $baremetricsService;

    public function __construct(BaremetricsService $baremetricsService)
    {
        parent::__construct();
        $this->baremetricsService = $baremetricsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 TESTING BAREMETRICS CREATE ENDPOINTS');
        $this->info('=====================================');
        
        $environment = config('services.baremetrics.environment');
        $this->info("🌍 Environment: {$environment}");
        
        if ($this->option('all')) {
            $this->testAll();
        } else {
            if ($this->option('test-sources')) {
                $this->testSources();
            }
            
            if ($this->option('test-customer')) {
                $this->testCustomer();
            }
            
            if ($this->option('test-plan')) {
                $this->testPlan();
            }
            
            if ($this->option('test-subscription')) {
                $this->testSubscription();
            }
            
            if ($this->option('test-complete')) {
                $this->testCompleteSetup();
            }
        }

        $this->info('✅ Testing completed');
        return 0;
    }

    /**
     * Test getting sources
     */
    private function testSources()
    {
        $this->info('🔍 Testing getSourceId()...');
        
        $sourceId = $this->baremetricsService->getSourceId();
        
        if ($sourceId) {
            $this->info("✅ Source ID obtained: {$sourceId}");
        } else {
            $this->error('❌ Failed to get source ID');
        }
        
        $this->newLine();
    }

    /**
     * Test customer creation
     */
    private function testCustomer()
    {
        $this->info('👤 Testing createCustomer()...');
        
        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No source ID available for testing');
            return;
        }

        $customerData = [
            'name' => 'Test Customer ' . now()->format('Y-m-d H:i:s'),
            'email' => 'test.customer@example.com',
            'company' => 'Test Company',
            'notes' => 'Test customer created via API'
        ];

        $result = $this->baremetricsService->createCustomer($customerData, $sourceId);
        
        if ($result) {
            $this->info('✅ Customer created successfully');
            $this->line('Customer OID: ' . ($result['customer']['oid'] ?? 'N/A'));
            $this->line('Customer Name: ' . ($result['customer']['name'] ?? 'N/A'));
            $this->line('Customer Email: ' . ($result['customer']['email'] ?? 'N/A'));
        } else {
            $this->error('❌ Failed to create customer');
        }
        
        $this->newLine();
    }

    /**
     * Test plan creation
     */
    private function testPlan()
    {
        $this->info('📋 Testing createPlan()...');
        
        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No source ID available for testing');
            return;
        }

        $planData = [
            'name' => 'Test Plan ' . now()->format('Y-m-d H:i:s'),
            'interval' => 'month',
            'interval_count' => 1,
            'amount' => 2999, // $29.99 in cents
            'currency' => 'USD',
            'trial_days' => 7,
            'notes' => 'Test plan created via API'
        ];

        $result = $this->baremetricsService->createPlan($sourceId, $planData);
        
        if ($result) {
            $this->info('✅ Plan created successfully');
            $this->line('Plan OID: ' . ($result['plan']['oid'] ?? 'N/A'));
            $this->line('Plan Name: ' . ($result['plan']['name'] ?? 'N/A'));
            $this->line('Plan Amount: $' . (($result['plan']['amount'] ?? 0) / 100));
        } else {
            $this->error('❌ Failed to create plan');
        }
        
        $this->newLine();
    }

    /**
     * Test subscription creation
     */
    private function testSubscription()
    {
        $this->info('🔄 Testing createSubscription()...');
        
        $sourceId = $this->baremetricsService->getSourceId();
        if (!$sourceId) {
            $this->error('❌ No source ID available for testing');
            return;
        }

        // First create a customer and plan
        $customerData = [
            'name' => 'Subscription Test Customer ' . now()->format('Y-m-d H:i:s'),
            'email' => 'subscription.test@example.com',
            'company' => 'Subscription Test Company'
        ];

        $customer = $this->baremetricsService->createCustomer($customerData, $sourceId);
        if (!$customer || !isset($customer['customer']['oid'])) {
            $this->error('❌ Failed to create customer for subscription test');
            return;
        }

        $planData = [
            'name' => 'Subscription Test Plan ' . now()->format('Y-m-d H:i:s'),
            'interval' => 'month',
            'interval_count' => 1,
            'amount' => 1999, // $19.99 in cents
            'currency' => 'USD'
        ];

        $plan = $this->baremetricsService->createPlan($sourceId, $planData);
        if (!$plan || !isset($plan['plan']['oid'])) {
            $this->error('❌ Failed to create plan for subscription test');
            return;
        }

        $subscriptionData = [
            'customer_oid' => $customer['customer']['oid'],
            'plan_oid' => $plan['plan']['oid'],
            'started_at' => now()->timestamp,
            'status' => 'active',
            'notes' => 'Test subscription created via API'
        ];

        $result = $this->baremetricsService->createSubscription($sourceId, $subscriptionData);
        
        if ($result) {
            $this->info('✅ Subscription created successfully');
            $this->line('Subscription OID: ' . ($result['subscription']['oid'] ?? 'N/A'));
            $this->line('Customer OID: ' . ($result['subscription']['customer_oid'] ?? 'N/A'));
            $this->line('Plan OID: ' . ($result['subscription']['plan_oid'] ?? 'N/A'));
            $this->line('Status: ' . ($result['subscription']['status'] ?? 'N/A'));
        } else {
            $this->error('❌ Failed to create subscription');
        }
        
        $this->newLine();
    }

    /**
     * Test complete customer setup
     */
    private function testCompleteSetup()
    {
        $this->info('🎯 Testing createCompleteCustomerSetup()...');
        
        $timestamp = now()->format('Y-m-d H:i:s');
        
        $customerData = [
            'name' => 'Complete Setup Customer ' . $timestamp,
            'email' => 'complete.setup@example.com',
            'company' => 'Complete Setup Company',
            'notes' => 'Customer created via complete setup API'
        ];

        $planData = [
            'name' => 'Complete Setup Plan ' . $timestamp,
            'interval' => 'month',
            'interval_count' => 1,
            'amount' => 4999, // $49.99 in cents
            'currency' => 'USD',
            'trial_days' => 14,
            'notes' => 'Plan created via complete setup API'
        ];

        $subscriptionData = [
            'started_at' => now()->timestamp,
            'status' => 'active',
            'notes' => 'Subscription created via complete setup API'
        ];

        $result = $this->baremetricsService->createCompleteCustomerSetup(
            $customerData, 
            $planData, 
            $subscriptionData
        );
        
        if ($result) {
            $this->info('✅ Complete customer setup created successfully');
            $this->line('Source ID: ' . ($result['source_id'] ?? 'N/A'));
            $this->line('Customer OID: ' . ($result['customer']['customer']['oid'] ?? 'N/A'));
            $this->line('Plan OID: ' . ($result['plan']['plan']['oid'] ?? 'N/A'));
            $this->line('Subscription OID: ' . ($result['subscription']['subscription']['oid'] ?? 'N/A'));
        } else {
            $this->error('❌ Failed to create complete customer setup');
        }
        
        $this->newLine();
    }

    /**
     * Test all endpoints
     */
    private function testAll()
    {
        $this->testSources();
        $this->testCustomer();
        $this->testPlan();
        $this->testSubscription();
        $this->testCompleteSetup();
    }
}
