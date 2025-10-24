<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Searching for email: melortegag@gmail.com ===\n\n";

$baremetricsService = app(BaremetricsService::class);

$email = 'melortegag@gmail.com';
echo "Searching for email: $email\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

$startTime = microtime(true);
$result = $baremetricsService->getCustomersByEmail($email);
$endTime = microtime(true);

$executionTime = $endTime - $startTime;
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Total time: " . round($executionTime, 2) . " seconds\n\n";

if ($result && is_array($result) && count($result) > 0) {
    echo "✅ CUSTOMER FOUND!\n\n";
    $customer = $result[0];
    echo "Customer Details:\n";
    echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
    echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
    echo "- OID: " . ($customer['oid'] ?? 'N/A') . "\n";
    echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";

    if (isset($customer['properties'])) {
        echo "- Properties: " . json_encode($customer['properties'], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ CUSTOMER NOT FOUND\n";
    echo "Result: " . (is_null($result) ? 'null' : json_encode($result)) . "\n";
}

echo "\n=== Search Test Completed ===\n";