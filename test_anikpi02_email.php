<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;
use App\Http\Controllers\CancellationController;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing CancellationController getCustomers with anikpi02@gmail.com ===\n\n";

// Initialize the controller
$cancellationController = new CancellationController(
    app(\App\Services\StripeService::class),
    app(BaremetricsService::class)
);

// Test the getCustomers method directly with the problematic email
echo "Testing getCustomers method with email: anikpi02@gmail.com\n";
echo "Starting search at: " . date('Y-m-d H:i:s') . "\n";

try {
    // Use reflection to access the private method
    $reflection = new ReflectionClass($cancellationController);
    $method = $reflection->getMethod('getCustomers');
    $method->setAccessible(true);

    $startTime = microtime(true);
    $customers = $method->invoke($cancellationController, 'anikpi02@gmail.com');
    $endTime = microtime(true);

    $executionTime = $endTime - $startTime;
    echo "Search completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "Execution time: " . round($executionTime, 2) . " seconds\n\n";

    if ($customers) {
        echo "Raw customers data structure:\n";
        var_dump($customers);

        // Handle different possible structures
        $customerList = [];
        if (isset($customers['customers']) && is_array($customers['customers'])) {
            $customerList = $customers['customers'];
        } elseif (is_array($customers)) {
            $customerList = array_filter($customers, function($item) {
                return is_array($item) && isset($item['email']);
            });
        }

        echo "\n✅ getCustomers returned data!\n";
        echo "Number of customers found: " . count($customerList) . "\n";

        if (!empty($customerList)) {
            // Show details of first customer
            $customer = reset($customerList); // Get first element regardless of key
            echo "\nCustomer details:\n";
            echo "- OID: " . ($customer['oid'] ?? 'N/A') . "\n";
            echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
            echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
            echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";

            // Check if this is the customer we're looking for
            if (isset($customer['email']) && strtolower($customer['email']) === 'anikpi02@gmail.com') {
                echo "\n✅ SUCCESS: Found the correct customer!\n";
            } else {
                echo "\n❌ Found a customer but not the one we're looking for\n";
            }
        } else {
            echo "❌ No valid customers found in the data\n";
        }
    } else {
        echo "❌ getCustomers returned null or empty data\n";
        var_dump($customers);
    }
} catch (Exception $e) {
    echo "❌ Error testing getCustomers: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";