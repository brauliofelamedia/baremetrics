<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\BaremetricsService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== Verificando estado actual de los clientes ===\n\n";

$customers = $service->getCustomers($sourceId, '', 1);

$testOids = [
    'ghl_68e93a90c27bf370610288',
    'ghl_68e93a91b745e131008559',
    'ghl_68e93a92ca0bf793917671',
    'ghl_68e93a93bdb4a619479177',
    'ghl_68e93a94c0b40763331690'
];

foreach ($customers['customers'] as $customer) {
    if (in_array($customer['oid'], $testOids)) {
        echo "📧 " . $customer['email'] . "\n";
        echo "   OID: " . $customer['oid'] . "\n";
        echo "   Activo: " . ($customer['is_active'] ? '✅ Sí' : '❌ No') . "\n";
        echo "   MRR actual: $" . $customer['current_mrr'] . "\n";
        echo "   Planes actuales: " . count($customer['current_plans']) . "\n";
        
        if (!empty($customer['current_plans'])) {
            foreach ($customer['current_plans'] as $plan) {
                echo "      → " . $plan . "\n";
            }
        }
        echo "\n";
    }
}
