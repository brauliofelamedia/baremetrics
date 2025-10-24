<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing API search functionality ===\n\n";

$baremetricsService = app(BaremetricsService::class);

// Test 1: Get customers without search parameter
echo "Test 1: Getting customers from first source without search filter\n";
$sources = $baremetricsService->getSources();
$sourceIds = [];

if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
    $sourceIds = array_column($sources['sources'], 'id');
} elseif (is_array($sources)) {
    $sourceIds = array_column($sources, 'id');
}

if (!empty($sourceIds)) {
    $firstSourceId = $sourceIds[0];
    echo "Using source: $firstSourceId\n";

    $startTime = microtime(true);
    $response = $baremetricsService->getCustomers($firstSourceId, '', 1); // No search parameter
    $endTime = microtime(true);

    echo "Time taken: " . round($endTime - $startTime, 2) . " seconds\n";

    if ($response && isset($response['customers'])) {
        $customers = $response['customers'];
        echo "Found " . count($customers) . " customers\n";

        if (count($customers) > 0) {
            echo "First 3 customers:\n";
            for ($i = 0; $i < min(3, count($customers)); $i++) {
                $customer = $customers[$i];
                echo "  - " . ($customer['name'] ?? 'N/A') . " (" . ($customer['email'] ?? 'N/A') . ")\n";
            }
        }
    } else {
        echo "❌ No customers found or error\n";
        var_dump($response);
    }
} else {
    echo "❌ No sources found\n";
}

echo "\n";

// Test 2: Try search with a known email pattern
echo "Test 2: Searching with partial email 'melorteg'\n";
if (!empty($sourceIds)) {
    $firstSourceId = $sourceIds[0];

    $startTime = microtime(true);
    $response = $baremetricsService->getCustomers($firstSourceId, 'melorteg', 1);
    $endTime = microtime(true);

    echo "Time taken: " . round($endTime - $startTime, 2) . " seconds\n";

    if ($response && isset($response['customers'])) {
        $customers = $response['customers'];
        echo "Found " . count($customers) . " customers matching 'melorteg'\n";

        foreach ($customers as $customer) {
            echo "  - " . ($customer['name'] ?? 'N/A') . " (" . ($customer['email'] ?? 'N/A') . ")\n";
        }
    } else {
        echo "❌ No customers found or error\n";
    }
}

echo "\n=== API Test Completed ===\n";