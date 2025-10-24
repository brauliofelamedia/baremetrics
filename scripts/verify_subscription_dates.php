<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baremetricsService = app(\App\Services\BaremetricsService::class);
$ghlService = app(\App\Services\GoHighLevelService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== VERIFICACIÓN DE FECHAS DE SUSCRIPCIONES ===\n\n";

// Obtener usuarios creados hoy
$customers = $baremetricsService->getCustomers($sourceId, '', 1);
$today = strtotime('today');
$testUsers = [];

foreach ($customers['customers'] as $customer) {
    if (strpos($customer['oid'], 'ghl_') === 0 && $customer['created'] >= $today) {
        $testUsers[] = $customer;
    }
}

echo "Encontrados " . count($testUsers) . " usuarios importados hoy\n\n";

if (count($testUsers) === 0) {
    echo "⚠️  No hay usuarios para verificar. Importa los usuarios primero.\n";
    exit;
}

// Obtener todas las suscripciones
$subscriptions = $baremetricsService->getSubscriptions($sourceId);

$correctDates = 0;
$incorrectDates = 0;

foreach ($testUsers as $index => $customer) {
    $num = $index + 1;
    echo "#{$num}. {$customer['email']}\n";
    
    // Buscar suscripciones del cliente
    $customerSubscriptions = [];
    
    if ($subscriptions && isset($subscriptions['subscriptions'])) {
        foreach ($subscriptions['subscriptions'] as $sub) {
            if (isset($sub['customer']['oid']) && $sub['customer']['oid'] === $customer['oid']) {
                $customerSubscriptions[] = $sub;
            }
        }
    }
    
    if (count($customerSubscriptions) > 0) {
        foreach ($customerSubscriptions as $sub) {
            $subDate = date('Y-m-d H:i:s', $sub['started_at']);
            echo "   Fecha suscripción: {$subDate}\n";
            
            // Obtener fecha real de GHL para comparar
            try {
                $ghlContact = $ghlService->getContacts($customer['email']);
                if ($ghlContact && isset($ghlContact['contacts']) && !empty($ghlContact['contacts'])) {
                    $contact = $ghlContact['contacts'][0];
                    $ghlCreated = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
                    
                    if ($ghlCreated) {
                        $ghlTimestamp = strtotime($ghlCreated);
                        $ghlDate = date('Y-m-d H:i:s', $ghlTimestamp);
                        echo "   Fecha GHL:          {$ghlDate}\n";
                        
                        // Comparar fechas (permitir hasta 1 hora de diferencia)
                        $diff = abs($sub['started_at'] - $ghlTimestamp);
                        if ($diff < 3600) { // 1 hora
                            echo "   ✅ FECHA CORRECTA\n";
                            $correctDates++;
                        } else {
                            echo "   ❌ FECHA INCORRECTA (diferencia: " . round($diff / 86400, 2) . " días)\n";
                            $incorrectDates++;
                        }
                    }
                }
            } catch (Exception $e) {
                echo "   ⚠️  Error: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "   ❌ Sin suscripciones\n";
    }
    
    echo "\n";
}

echo "========================================\n";
echo "RESUMEN:\n";
echo "  Fechas correctas:   {$correctDates}\n";
echo "  Fechas incorrectas: {$incorrectDates}\n";
echo "========================================\n";
