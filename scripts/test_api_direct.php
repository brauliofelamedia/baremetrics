<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$apiKey = config('services.baremetrics.production_api_key');
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== TEST DIRECTO A API BAREMETRICS ===" . PHP_EOL . PHP_EOL;
echo "API Key: " . substr($apiKey, 0, 10) . "..." . PHP_EOL;
echo "Source ID: {$sourceId}" . PHP_EOL . PHP_EOL;

// Test 1: Obtener account info
echo "1. Verificando cuenta..." . PHP_EOL;
$ch = curl_init("https://api.baremetrics.com/v1/account");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey}",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   ✓ Cuenta activa: " . ($data['account']['company'] ?? 'N/A') . PHP_EOL;
} else {
    echo "   ✗ Error: HTTP {$httpCode}" . PHP_EOL;
    echo "   Response: {$response}" . PHP_EOL;
}

echo PHP_EOL;

// Test 2: Obtener clientes
echo "2. Obteniendo clientes del source..." . PHP_EOL;
$ch = curl_init("https://api.baremetrics.com/v1/{$sourceId}/customers?page=1&per_page=100");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$apiKey}",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: {$httpCode}" . PHP_EOL;

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $totalCustomers = count($data['customers'] ?? []);
    echo "   ✓ Total clientes: {$totalCustomers}" . PHP_EOL . PHP_EOL;
    
    // Buscar nuestros clientes
    $ourOids = [
        'ghl_68e93a90c27bf370610288',
        'ghl_68e93a91b745e131008559',
        'ghl_68e93a92ca0bf793917671',
        'ghl_68e93a93bdb4a619479177',
        'ghl_68e93a94c0b40763331690'
    ];
    
    $found = 0;
    if (isset($data['customers'])) {
        foreach ($data['customers'] as $customer) {
            if (in_array($customer['oid'], $ourOids)) {
                $found++;
                echo "   ✓ Encontrado: " . $customer['email'] . " (OID: " . $customer['oid'] . ")" . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "   Total encontrados: {$found} de 5" . PHP_EOL;
    
} else {
    echo "   ✗ Error obteniendo clientes" . PHP_EOL;
    echo "   Response: " . substr($response, 0, 500) . PHP_EOL;
}
