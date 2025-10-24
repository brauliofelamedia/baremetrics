<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;
use App\Http\Controllers\CancellationController;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Final Performance Test for getCustomersByEmail ===\n\n";

// Initialize the controller
$cancellationController = new CancellationController(
    app(\App\Services\StripeService::class),
    app(BaremetricsService::class)
);

// Test emails
$testEmails = [
    'danikpi02@gmail.com' => 'Should exist',
    'anikpi02@gmail.com' => 'Should NOT exist',
    'test@example.com' => 'Should NOT exist'
];

foreach ($testEmails as $email => $expected) {
    echo "=== Testing email: $email ($expected) ===\n";
    echo "Starting search at: " . date('Y-m-d H:i:s') . "\n";

    try {
        // Use reflection to access the private method
        $reflection = new ReflectionClass($cancellationController);
        $method = $reflection->getMethod('getCustomers');
        $method->setAccessible(true);

        $startTime = microtime(true);
        $customers = $method->invoke($cancellationController, $email);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;
        echo "Search completed at: " . date('Y-m-d H:i:s') . "\n";
        echo "Execution time: " . round($executionTime, 2) . " seconds\n";

        if ($customers) {
            // Handle different possible structures
            $customerList = [];
            if (isset($customers['customers']) && is_array($customers['customers'])) {
                $customerList = $customers['customers'];
            } elseif (is_array($customers)) {
                $customerList = array_filter($customers, function($item) {
                    return is_array($item) && isset($item['email']);
                });
            }

            echo "✅ Found " . count($customerList) . " customers\n";

            if (!empty($customerList)) {
                $customer = reset($customerList);
                echo "Customer: " . ($customer['name'] ?? 'N/A') . " <" . ($customer['email'] ?? 'N/A') . ">\n";
                echo "✅ SUCCESS: Email found as expected\n";
            }
        } else {
            echo "❌ No customers found (null result)\n";
            if (strpos($expected, 'NOT') !== false) {
                echo "✅ CORRECT: Email not found as expected\n";
            } else {
                echo "❌ UNEXPECTED: Email should exist but was not found\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== Performance Test Completed ===\n";