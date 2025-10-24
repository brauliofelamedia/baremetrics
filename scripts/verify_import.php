<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(App\Services\BaremetricsService::class);
config(['services.baremetrics.environment' => 'production']);
$service->reinitializeConfiguration();
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

$ourOids = [
    'ghl_68e93a90c27bf370610288',
    'ghl_68e93a91b745e131008559',
    'ghl_68e93a92ca0bf793917671',
    'ghl_68e93a93bdb4a619479177',
    'ghl_68e93a94c0b40763331690'
];

echo "=== VERIFICACIÓN EN BAREMETRICS ===" . PHP_EOL . PHP_EOL;

// Obtener clientes
$response = $service->getCustomers($sourceId, 1, 100);
echo "Total clientes en respuesta: " . (isset($response['customers']) ? count($response['customers']) : 0) . PHP_EOL . PHP_EOL;

// Obtener suscripciones
$subsResponse = $service->getSubscriptions($sourceId, 1, 100);
echo "Total suscripciones en respuesta: " . (isset($subsResponse['subscriptions']) ? count($subsResponse['subscriptions']) : 0) . PHP_EOL . PHP_EOL;

if (isset($response['customers'])) {
    $found = 0;
    foreach ($response['customers'] as $customer) {
        if (in_array($customer['oid'], $ourOids)) {
            $found++;
            echo "✓ Cliente #{$found}: " . $customer['email'] . PHP_EOL;
            echo "  OID: " . $customer['oid'] . PHP_EOL;
            echo "  Nombre: " . $customer['name'] . PHP_EOL;
            echo "  MRR: $" . ($customer['current_mrr'] / 100) . PHP_EOL;
            echo "  Activo: " . ($customer['is_active'] ? 'Sí' : 'No') . PHP_EOL;
            echo "  Planes actuales: " . count($customer['current_plans']) . PHP_EOL;
            
            // Buscar suscripciones de este cliente
            if (isset($subsResponse['subscriptions'])) {
                $customerSubs = [];
                foreach ($subsResponse['subscriptions'] as $sub) {
                    $subCustomerOid = $sub['customer_oid'] ?? $sub['customer']['oid'] ?? null;
                    if ($subCustomerOid === $customer['oid']) {
                        $customerSubs[] = $sub;
                    }
                }
                
                echo "  Suscripciones: " . count($customerSubs) . PHP_EOL;
                foreach ($customerSubs as $sub) {
                    echo "    - Plan: " . ($sub['plan']['name'] ?? 'N/A') . PHP_EOL;
                    echo "      Estado: " . ($sub['status'] ?? 'N/A') . PHP_EOL;
                    echo "      OID Sub: " . ($sub['oid'] ?? 'N/A') . PHP_EOL;
                }
            }
            
            echo PHP_EOL;
        }
    }
    
    echo "Total encontrados: {$found} de 5" . PHP_EOL;
}
