<?php

require_once __DIR__ . '/vendor/autoload.php';

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\BaremetricsService;

echo "=== Checking available sources ===\n\n";

$baremetricsService = app(BaremetricsService::class);

$sources = $baremetricsService->getSources();

if ($sources && is_array($sources)) {
    if (isset($sources['sources']) && is_array($sources['sources'])) {
        $sourcesList = $sources['sources'];
    } elseif (is_array($sources)) {
        $sourcesList = $sources;
    } else {
        $sourcesList = [];
    }

    echo "Found " . count($sourcesList) . " sources:\n\n";

    foreach ($sourcesList as $index => $source) {
        echo ($index + 1) . ". ID: " . ($source['id'] ?? 'N/A') . "\n";
        echo "   Name: " . ($source['name'] ?? 'N/A') . "\n";
        echo "   Provider: " . ($source['provider'] ?? 'N/A') . "\n";
        echo "   Active: " . (isset($source['active']) ? ($source['active'] ? 'Yes' : 'No') : 'N/A') . "\n\n";
    }
} else {
    echo "❌ No sources found or error retrieving sources\n";
    echo "Result: " . json_encode($sources) . "\n";
}

echo "\n=== Sources check completed ===\n";