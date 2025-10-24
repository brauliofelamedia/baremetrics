<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Debug getCustomersByEmail directly ===\n\n";

$baremetricsService = app(BaremetricsService::class);

$email = 'danikpi02@gmail.com';
echo "Testing getCustomersByEmail directly with: $email\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n";

$startTime = microtime(true);
$result = $baremetricsService->getCustomersByEmail($email);
$endTime = microtime(true);

$executionTime = $endTime - $startTime;
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
echo "Execution time: " . round($executionTime, 2) . " seconds\n\n";

echo "Result:\n";
var_dump($result);

if ($result && is_array($result) && !empty($result)) {
    echo "\n✅ Found customer!\n";
    $customer = $result[0];
    echo "Name: " . ($customer['name'] ?? 'N/A') . "\n";
    echo "Email: " . ($customer['email'] ?? 'N/A') . "\n";
    echo "OID: " . ($customer['oid'] ?? 'N/A') . "\n";
} else {
    echo "\n❌ No customer found or null result\n";
}