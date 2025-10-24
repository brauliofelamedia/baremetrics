<?php

require_once __DIR__ . '/vendor/autoload.php';

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\CancellationController;

echo "=== Testing surveyCancellation method ===\n\n";

$controller = new CancellationController(
    app(\App\Services\StripeService::class),
    app(\App\Services\BaremetricsService::class)
);

// Test the surveyCancellation method
$customerId = 'cus_QxVRFKAf5DKL7q';

echo "Testing surveyCancellation with customer_id: $customerId\n";

try {
    // Use reflection to call the private method
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('surveyCancellation');
    $method->setAccessible(true);

    $result = $method->invoke($controller, $customerId);

    echo "Method executed successfully\n";
    echo "Result type: " . gettype($result) . "\n";

    if (is_object($result) && method_exists($result, 'getName')) {
        echo "View name: " . $result->getName() . "\n";
        echo "View data: " . json_encode($result->getData(), JSON_PRETTY_PRINT) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";