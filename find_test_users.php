<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\BaremetricsService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== Buscando usuarios de prueba recientes ===\n\n";

$customers = $service->getCustomers($sourceId, '', 1);

// Buscar usuarios con prefijo ghl_ creados hoy
$today = strtotime('today');
$testUsers = [];

foreach ($customers['customers'] as $customer) {
    if (strpos($customer['oid'], 'ghl_') === 0 && $customer['created'] >= $today) {
        $testUsers[] = $customer;
    }
}

echo "Encontrados " . count($testUsers) . " usuarios creados hoy con prefijo ghl_\n\n";

if (count($testUsers) > 0) {
    foreach ($testUsers as $user) {
        echo "📧 Email: " . $user['email'] . "\n";
        echo "   OID: " . $user['oid'] . "\n";
        echo "   Created: " . date('Y-m-d H:i:s', $user['created']) . "\n";
        
        // Verificar si tiene el campo GHL: Migrate GHL
        $hasMigrate = false;
        if (isset($user['properties']) && is_array($user['properties'])) {
            foreach ($user['properties'] as $prop) {
                if (isset($prop['name']) && $prop['name'] === 'GHL: Migrate GHL') {
                    echo "   GHL Migrate: " . ($prop['value'] ?? 'N/A') . "\n";
                    $hasMigrate = true;
                }
            }
        }
        
        if (!$hasMigrate) {
            echo "   GHL Migrate: ❌ No configurado\n";
        }
        
        echo "\n";
    }
} else {
    echo "No se encontraron usuarios de prueba recientes.\n";
}
