<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\BaremetricsService;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing BaremetricsService ===\n\n";

// Initialize the service
$baremetricsService = app(BaremetricsService::class);

echo "Environment: " . $baremetricsService->getEnvironment() . "\n";
echo "Base URL: " . $baremetricsService->getBaseUrl() . "\n\n";

// Test 1: Get all customers using getCustomersAll
echo "=== Test 1: getCustomersAll ===\n";

try {
    // Get a source ID first
    $sourceId = $baremetricsService->getGHLSourceId();
    echo "Using Source ID: $sourceId\n";

    if ($sourceId) {
        $customers = $baremetricsService->getCustomersAll($sourceId, 0);

        if ($customers) {
            echo "✅ getCustomersAll successful!\n";
            $customersList = $customers['customers'] ?? [];
            echo "Total customers found: " . count($customersList) . "\n";

            // Show first 3 customers as example
            if (!empty($customersList)) {
                echo "\nFirst 3 customers:\n";
                for ($i = 0; $i < min(3, count($customersList)); $i++) {
                    $customer = $customersList[$i];
                    echo "- ID: " . ($customer['id'] ?? 'N/A') . ", Email: " . ($customer['email'] ?? 'N/A') . ", Name: " . ($customer['name'] ?? 'N/A') . "\n";
                }
            }
        } else {
            echo "❌ getCustomersAll returned null\n";
        }
    } else {
        echo "❌ Could not get source ID\n";
    }
} catch (Exception $e) {
    echo "❌ Error in getCustomersAll: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// Test 2: Get customer by email using getCustomersByEmail
echo "=== Test 2: getCustomersByEmail ===\n";

try {
    // Use a test email - using a real email from the customers we found
    $testEmail = "danikpi02@gmail.com"; // This email exists in the data

    echo "Searching for email: $testEmail\n";

    $customerByEmail = $baremetricsService->getCustomersByEmail($testEmail);

    if ($customerByEmail && is_array($customerByEmail) && !empty($customerByEmail)) {
        echo "✅ getCustomersByEmail successful!\n";
        echo "Customer found:\n";
        $customer = $customerByEmail[0]; // It returns an array
        echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";
        echo "- OID: " . ($customer['oid'] ?? 'N/A') . "\n";
        echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
        echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
    } elseif ($customerByEmail === null) {
        echo "ℹ️  No customer found with email: $testEmail\n";
        echo "This is normal if the email doesn't exist in Baremetrics\n";
    } else {
        echo "❌ getCustomersByEmail returned unexpected result\n";
        var_dump($customerByEmail);
    }
} catch (Exception $e) {
    echo "❌ Error in getCustomersByEmail: " . $e->getMessage() . "\n";
}

echo "\n=== Additional Test: Check Available Sources ===\n";

try {
    $sources = $baremetricsService->getSources();
    if ($sources) {
        echo "Available sources:\n";
        $sourcesList = [];
        if (is_array($sources) && isset($sources['sources']) && is_array($sources['sources'])) {
            $sourcesList = $sources['sources'];
        } elseif (is_array($sources)) {
            $sourcesList = $sources;
        }

        foreach ($sourcesList as $source) {
            echo "- ID: " . ($source['id'] ?? 'N/A') . ", Provider: " . ($source['provider'] ?? 'N/A') . ", Name: " . ($source['name'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ Could not get sources\n";
    }
} catch (Exception $e) {
    echo "❌ Error getting sources: " . $e->getMessage() . "\n";
}

echo "\n=== Test 3: Search in GHL Source ===\n";

try {
    $ghlSourceId = $baremetricsService->getGHLSourceId();
    echo "Searching for email in GHL source: $ghlSourceId\n";

    $customers = $baremetricsService->getCustomersAll($ghlSourceId, 0);
    if ($customers && isset($customers['customers'])) {
        foreach ($customers['customers'] as $customer) {
            if (isset($customer['email']) && strtolower($customer['email']) === strtolower($testEmail)) {
                echo "✅ Customer found in GHL source!\n";
                echo "- Email: " . ($customer['email'] ?? 'N/A') . "\n";
                echo "- Name: " . ($customer['name'] ?? 'N/A') . "\n";
                echo "- ID: " . ($customer['id'] ?? 'N/A') . "\n";
                break;
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Error in GHL source search: " . $e->getMessage() . "\n";
}