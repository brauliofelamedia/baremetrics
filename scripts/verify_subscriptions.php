<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\BaremetricsService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "Verificando suscripciones de los 5 usuarios de prueba...\n\n";

$testUsers = [
    ['email' => 'yuvianat.holisticcoach@gmail.com', 'oid' => 'ghl_68e93a90c27bf370610288'],
    ['email' => 'lizzleony@gmail.com', 'oid' => 'ghl_68e93a91b745e131008559'],
    ['email' => 'marisolkfitbyme@gmail.com', 'oid' => 'ghl_68e93a92ca0bf793917671'],
    ['email' => 'ninfa.cardozo.lopez@gmail.com', 'oid' => 'ghl_68e93a93bdb4a619479177'],
    ['email' => 'horopeza8@gmail.com', 'oid' => 'ghl_68e93a94c0b40763331690']
];

try {
    $subscriptions = $service->getSubscriptions($sourceId);
    
    if ($subscriptions && isset($subscriptions['subscriptions'])) {
        echo "Total de suscripciones en Baremetrics: " . count($subscriptions['subscriptions']) . "\n\n";
        
        foreach ($testUsers as $user) {
            $found = false;
            foreach ($subscriptions['subscriptions'] as $sub) {
                if (isset($sub['customer']['oid']) && $sub['customer']['oid'] == $user['oid']) {
                    echo "✅ " . $user['email'] . "\n";
                    echo "   OID Cliente: " . $sub['customer']['oid'] . "\n";
                    echo "   OID Suscripción: " . $sub['oid'] . "\n";
                    echo "   Plan: " . ($sub['plan']['name'] ?? 'N/A') . "\n";
                    echo "   Activa: " . ($sub['active'] ? 'Sí' : 'No') . "\n";
                    echo "   MRR: $" . $sub['mrr'] . "\n\n";
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                echo "❌ " . $user['email'] . " - Sin suscripción encontrada\n\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
