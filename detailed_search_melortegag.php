<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Detailed Debug Search for melortegag@gmail.com ===\n\n";

$baremetricsService = app(BaremetricsService::class);

$email = 'melortegag@gmail.com';
echo "Searching for email: $email\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$startTime = microtime(true);

// Get sources first
$sources = $baremetricsService->getSources();
if (!$sources) {
    echo "❌ No sources found!\n";
    exit(1);
}

// Normalize sources
$sourcesNew = [];
if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
    $sourcesNew = $sources['sources'];
} elseif (is_array($sources)) {
    $sourcesNew = $sources;
}

$sourceIds = array_values(array_filter(array_column($sourcesNew, 'id'), function ($id) {
    return !empty($id);
}));

echo "Found " . count($sourceIds) . " sources to search:\n";
foreach ($sourceIds as $index => $sourceId) {
    echo "  " . ($index + 1) . ". $sourceId\n";
}
echo "\n";

// Search in each source with detailed logging
foreach ($sourceIds as $sourceIndex => $sourceId) {
    echo "🔍 Searching in source " . ($sourceIndex + 1) . "/4: $sourceId\n";

    $page = 1;
    $hasMore = true;
    $maxPages = 10; // Increased limit for debugging
    $pagesChecked = 0;
    $sourceStartTime = microtime(true);

    while ($hasMore && $pagesChecked < $maxPages) {
        echo "  📄 Checking page $page...\n";

        $pageStartTime = microtime(true);
        $response = $baremetricsService->getCustomers($sourceId, $email, $page);
        $pageEndTime = microtime(true);

        $pageTime = $pageEndTime - $pageStartTime;
        echo "    ⏱️  Page $page took " . round($pageTime, 2) . " seconds\n";

        if (!$response) {
            echo "    ❌ No response from API for page $page\n";
            break;
        }

        $customers = [];
        if (is_array($response) && isset($response['customers']) && is_array($response['customers'])) {
            $customers = $response['customers'];
        } elseif (is_array($response)) {
            $customers = $response;
        }

        echo "    👥 Found " . count($customers) . " customers on page $page\n";

        // Check each customer
        foreach ($customers as $customer) {
            $customerEmail = $customer['email'] ?? '';
            echo "      - Customer: " . ($customer['name'] ?? 'N/A') . " ($customerEmail)\n";

            if (isset($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
                echo "\n🎉 CUSTOMER FOUND in source $sourceId, page $page!\n\n";
                $endTime = microtime(true);
                $totalTime = $endTime - $startTime;

                echo "Customer Details:\n";
                echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
                echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
                echo "- OID: " . ($customer['oid'] ?? 'N/A') . "\n";
                echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";
                echo "- Total search time: " . round($totalTime, 2) . " seconds\n";

                if (isset($customer['properties'])) {
                    echo "- Properties: " . json_encode($customer['properties'], JSON_PRETTY_PRINT) . "\n";
                }

                echo "\n=== Search Completed Successfully ===\n";
                exit(0);
            }
        }

        // Check pagination
        if (isset($response['meta']['pagination'])) {
            $pagination = $response['meta']['pagination'];
            $hasMore = $pagination['has_more'] ?? false;
            echo "    📊 Has more pages: " . ($hasMore ? 'Yes' : 'No') . "\n";
        } else {
            $hasMore = false;
            echo "    📊 No pagination info, assuming no more pages\n";
        }

        $page++;
        $pagesChecked++;

        if ($hasMore && $pagesChecked < $maxPages) {
            echo "    😴 Sleeping 50ms before next page...\n";
            usleep(50000);
        }
    }

    $sourceEndTime = microtime(true);
    $sourceTime = $sourceEndTime - $sourceStartTime;
    echo "  ✅ Finished source $sourceId in " . round($sourceTime, 2) . " seconds ($pagesChecked pages checked)\n\n";
}

$endTime = microtime(true);
$totalTime = $endTime - $startTime;

echo "❌ CUSTOMER NOT FOUND after checking all sources\n";
echo "Total search time: " . round($totalTime, 2) . " seconds\n";
echo "Checked " . count($sourceIds) . " sources, up to 10 pages each\n\n";

echo "=== Detailed Search Completed ===\n";