<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;
use App\Http\Controllers\CancellationController;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing CancellationController getCustomers method ===\n\n";

// Initialize the controller
$cancellationController = new CancellationController(
    app(\App\Services\StripeService::class),
    app(BaremetricsService::class)
);

// Test the getCustomers method directly
echo "Testing getCustomers method with email: danikpi02@gmail.com\n";

try {
    // Use reflection to access the private method
    $reflection = new ReflectionClass($cancellationController);
    $method = $reflection->getMethod('getCustomers');
    $method->setAccessible(true);

    $customers = $method->invoke($cancellationController, 'danikpi02@gmail.com');

    echo "Raw customers data structure:\n";
    var_dump($customers);

    if ($customers && is_array($customers)) {
        echo "\n✅ getCustomers returned data!\n";

        // Handle different possible structures
        $customerList = [];
        if (isset($customers['customers']) && is_array($customers['customers'])) {
            $customerList = $customers['customers'];
        } elseif (isset($customers[0])) {
            $customerList = $customers;
        } else {
            // Maybe it's a flat array of customers
            $customerList = array_filter($customers, function($item) {
                return is_array($item) && isset($item['email']);
            });
        }

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
            if (isset($customer['email']) && strtolower($customer['email']) === 'danikpi02@gmail.com') {
                echo "\n✅ SUCCESS: Found the correct customer!\n";
            } else {
                echo "\n❌ Found a customer but not the one we're looking for\n";
            }
        } else {
            echo "❌ No valid customers found in the data\n";
        }
    } else {
        echo "❌ getCustomers returned no data\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing getCustomers: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";