<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BaremetricsService;

class TestBaremetricsCommand extends Command
{
    protected $signature = 'test:baremetrics';
    protected $description = 'Test Baremetrics API connection and sandbox creation';

    public function handle()
    {
        $service = app(BaremetricsService::class);
        
        $this->info('Testing Baremetrics configuration...');
        $this->info('Environment: ' . $service->getEnvironment());
        $this->info('Base URL: ' . $service->getBaseUrl());
        $this->info('Is Sandbox: ' . ($service->isSandbox() ? 'Yes' : 'No'));
        
        $this->info('Getting sources...');
        $sources = $service->getSources();
        
        if ($sources) {
            $this->info('Sources found: ' . count($sources['sources'] ?? []));
            if (!empty($sources['sources'])) {
                $sourceId = $sources['sources'][0]['id'];
                $this->info('First source ID: ' . $sourceId);
                
                // Test getting existing plans
                $this->info('Getting existing plans...');
                $existingPlans = $service->getPlans($sourceId);
                
                if ($existingPlans) {
                    $this->info('Existing plans found: ' . count($existingPlans['plans'] ?? []));
                    if (!empty($existingPlans['plans'])) {
                        foreach ($existingPlans['plans'] as $plan) {
                            $this->info('Plan: ' . $plan['name'] . ' (OID: ' . ($plan['oid'] ?? 'N/A') . ')');
                        }
                    }
                } else {
                    $this->error('Could not get existing plans');
                }
                
                // Test creating a single plan
                $this->info('Testing plan creation...');
                $testPlan = [
                    'name' => 'Test Plan ' . time(),
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => 1000,
                    'currency' => 'USD',
                    'oid' => 'test_plan_' . time(),
                ];
                
                $result = $service->createPlan($testPlan, $sourceId);
                
                if ($result) {
                    $this->info('Plan created successfully!');
                    $this->info('Plan ID: ' . ($result['plan']['id'] ?? 'N/A'));
                } else {
                    $this->error('Plan creation failed');
                }
            }
        } else {
            $this->error('No sources found or error occurred');
        }
        
        return 0;
    }
}