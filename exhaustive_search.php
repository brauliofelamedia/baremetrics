<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Manual search for melortegag@gmail.com across all pages ===\n\n";

$baremetricsService = app(BaremetricsService::class);

$email = 'melortegag@gmail.com';
echo "Searching for email: $email\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$startTime = microtime(true);

// Get sources
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

echo "Found " . count($sourceIds) . " sources to search:\n";
foreach ($sourceIds as $index => $sourceId) {
    echo "  " . ($index + 1) . ". $sourceId\n";
}
echo "\n";

// Search manually through all pages of each source
foreach ($sourceIds as $sourceIndex => $sourceId) {
    echo "🔍 Searching source " . ($sourceIndex + 1) . "/4: $sourceId\n";

    $page = 1;
    $maxPages = 50; // Much higher limit
    $pagesChecked = 0;
    $customersChecked = 0;
    $sourceStartTime = microtime(true);

    while ($pagesChecked < $maxPages) {
        echo "  📄 Checking page $page...\n";

        $pageStartTime = microtime(true);
        // Get customers without search filter
        $response = $baremetricsService->getCustomers($sourceId, '', $page);
        $pageEndTime = microtime(true);

        $pageTime = $pageEndTime - $pageStartTime;
        echo "    ⏱️  Page $page took " . round($pageTime, 2) . " seconds\n";

        if (!$response || !isset($response['customers'])) {
            echo "    ❌ No response or no customers array\n";
            break;
        }

        $customers = $response['customers'];
        echo "    👥 Found " . count($customers) . " customers on page $page\n";

        if (count($customers) === 0) {
            echo "    📊 No customers on this page, stopping pagination\n";
            break;
        }

        // Check each customer manually
        foreach ($customers as $customer) {
            $customersChecked++;
            $customerEmail = strtolower($customer['email'] ?? '');

            if ($customerEmail === strtolower($email)) {
                echo "\n🎉 CUSTOMER FOUND in source $sourceId, page $page!\n\n";
                $endTime = microtime(true);
                $totalTime = $endTime - $startTime;

                echo "Customer Details:\n";
                echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
                echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
                echo "- OID: " . ($customer['oid'] ?? 'N/A') . "\n";
                echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";
                echo "- Total search time: " . round($totalTime, 2) . " seconds\n";
                echo "- Pages checked: $pagesChecked\n";
                echo "- Customers checked: $customersChecked\n";

                if (isset($customer['properties'])) {
                    echo "- Properties: " . json_encode($customer['properties'], JSON_PRETTY_PRINT) . "\n";
                }

                echo "\n=== Search Completed Successfully ===\n";
                exit(0);
            }
        }

        // Check if there are more pages
        $hasMore = false;
        if (isset($response['meta']['pagination']['has_more'])) {
            $hasMore = $response['meta']['pagination']['has_more'];
        }

        echo "    📊 Has more pages: " . ($hasMore ? 'Yes' : 'No') . "\n";

        if (!$hasMore) {
            break;
        }

        $page++;
        $pagesChecked++;

        if ($pagesChecked < $maxPages) {
            echo "    😴 Sleeping 50ms before next page...\n";
            usleep(50000);
        }
    }

    $sourceEndTime = microtime(true);
    $sourceTime = $sourceEndTime - $sourceStartTime;
    echo "  ✅ Finished source $sourceId in " . round($sourceTime, 2) . " seconds\n";
    echo "     Pages checked: $pagesChecked, Customers checked: $customersChecked\n\n";
}

$endTime = microtime(true);
$totalTime = $endTime - $startTime;

echo "❌ CUSTOMER NOT FOUND after exhaustive search\n";
echo "Total search time: " . round($totalTime, 2) . " seconds\n";
echo "Checked all pages in " . count($sourceIds) . " sources\n\n";

echo "=== Exhaustive Search Completed ===\n";