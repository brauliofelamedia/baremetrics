<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Baremetrics API search parameter ===\n\n";

$baremetricsService = app(BaremetricsService::class);

// Get first source
$sources = $baremetricsService->getSources();
$sourceIds = [];

if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
    $sourceIds = array_column($sources['sources'], 'id');
} elseif (is_array($sources)) {
    $sourceIds = array_column($sources, 'id');
}

if (empty($sourceIds)) {
    echo "❌ No sources found\n";
    exit(1);
}

$firstSourceId = $sourceIds[0];
echo "Testing with source: $firstSourceId\n\n";

$email = 'melortegag@gmail.com';

// Test 1: Search with full email
echo "Test 1: Searching with full email '$email'\n";
$startTime = microtime(true);
$response = $baremetricsService->getCustomers($firstSourceId, $email, 1);
$endTime = microtime(true);

echo "Time: " . round($endTime - $startTime, 2) . " seconds\n";

if ($response && isset($response['customers'])) {
    $customers = $response['customers'];
    echo "Found " . count($customers) . " customers\n";

    if (count($customers) > 0) {
        echo "Customers returned:\n";
        foreach ($customers as $customer) {
            echo "  - " . ($customer['name'] ?? 'N/A') . " (" . ($customer['email'] ?? 'N/A') . ")\n";
        }
    }
} else {
    echo "❌ No response or no customers array\n";
    var_dump($response);
}

echo "\n";

// Test 2: Search with partial email
echo "Test 2: Searching with partial email 'melorteg'\n";
$startTime = microtime(true);
$response = $baremetricsService->getCustomers($firstSourceId, 'melorteg', 1);
$endTime = microtime(true);

echo "Time: " . round($endTime - $startTime, 2) . " seconds\n";

if ($response && isset($response['customers'])) {
    $customers = $response['customers'];
    echo "Found " . count($customers) . " customers\n";

    if (count($customers) > 0) {
        echo "Customers returned:\n";
        foreach ($customers as $customer) {
            echo "  - " . ($customer['name'] ?? 'N/A') . " (" . ($customer['email'] ?? 'N/A') . ")\n";
        }
    }
} else {
    echo "❌ No response or no customers array\n";
}

echo "\n";

// Test 3: Check what the actual URL looks like
echo "Test 3: Checking URL construction\n";
$testSearch = 'melortegag@gmail.com';
$testPage = 1;
$url = 'https://api.baremetrics.com/v1/' . $firstSourceId . '/customers?search=' . urlencode($testSearch) . '&sort=created&page=' . $testPage . '&order=asc&per_page=100';
echo "URL would be: $url\n";

echo "\n=== API Search Test Completed ===\n";