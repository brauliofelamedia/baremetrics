<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baremetricsService = app(\App\Services\BaremetricsService::class);
$ghlService = app(\App\Services\GoHighLevelService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== VERIFICACIÓN DE USUARIOS IMPORTADOS ===\n\n";

// Obtener usuarios creados hoy
$customers = $baremetricsService->getCustomers($sourceId, '', 1);
$today = strtotime('today');
$testUsers = [];

foreach ($customers['customers'] as $customer) {
    if (strpos($customer['oid'], 'ghl_') === 0 && $customer['created'] >= $today) {
        $testUsers[] = $customer;
    }
}

echo "✅ Encontrados " . count($testUsers) . " usuarios importados hoy\n\n";

if (count($testUsers) === 0) {
    echo "⚠️  No hay usuarios para verificar. Importa los usuarios primero.\n";
    exit;
}

// Obtener todas las suscripciones
$subscriptions = $baremetricsService->getSubscriptions($sourceId);

echo "=================================================\n";
echo "VERIFICACIÓN DETALLADA\n";
echo "=================================================\n\n";

foreach ($testUsers as $index => $customer) {
    $num = $index + 1;
    echo "👤 USUARIO #{$num}: {$customer['email']}\n";
    echo str_repeat("-", 80) . "\n";
    
    // Información básica del cliente
    echo "📋 DATOS DEL CLIENTE:\n";
    echo "   OID Baremetrics: {$customer['oid']}\n";
    echo "   Nombre: {$customer['name']}\n";
    echo "   Fecha creación en Baremetrics: " . date('Y-m-d H:i:s', $customer['created']) . "\n";
    echo "   Activo: " . ($customer['is_active'] ? '✅ Sí' : '❌ No') . "\n";
    echo "   MRR: $" . $customer['current_mrr'] . "\n";
    echo "\n";
    
    // Verificar custom fields
    echo "📝 CUSTOM FIELDS:\n";
    $hasGHLMigrate = false;
    
    if (isset($customer['attributes']) && is_array($customer['attributes'])) {
        $fieldsToCheck = [
            'GHL: Migrate GHL',
            'Engagement Score',
            'Subscriptions',
            'Country',
            'State',
            'Location'
        ];
        
        foreach ($customer['attributes'] as $attr) {
            $name = $attr['name'] ?? 'Unknown';
            $value = $attr['value'] ?? 'N/A';
            
            if ($name === 'GHL: Migrate GHL') {
                $hasGHLMigrate = true;
                echo "   ✅ {$name}: {$value}\n";
            } elseif (in_array($name, $fieldsToCheck)) {
                echo "   • {$name}: {$value}\n";
            }
        }
        
        if (!$hasGHLMigrate) {
            echo "   ❌ GHL: Migrate GHL: NO CONFIGURADO\n";
        }
    } else {
        echo "   ⚠️  No se encontraron attributes\n";
    }
    echo "\n";
    
    // Buscar suscripciones del cliente
    echo "📅 SUSCRIPCIONES:\n";
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
            echo "   OID: {$sub['oid']}\n";
            echo "   Plan: " . ($sub['plan']['name'] ?? 'N/A') . "\n";
            echo "   Estado Activo: " . ($sub['active'] ? '✅ Sí' : '❌ No') . "\n";
            echo "   MRR: $" . $sub['mrr'] . "\n";
            echo "   Cantidad: " . $sub['quantity'] . "\n";
            echo "   Fecha inicio (started_at): " . date('Y-m-d H:i:s', $sub['started_at']) . "\n";
            
            // Obtener fecha real de GHL para comparar
            try {
                $ghlContact = $ghlService->getContacts($customer['email']);
                if ($ghlContact && isset($ghlContact['contacts']) && !empty($ghlContact['contacts'])) {
                    $contact = $ghlContact['contacts'][0];
                    $ghlCreated = $contact['dateAdded'] ?? $contact['dateCreated'] ?? null;
                    
                    if ($ghlCreated) {
                        $ghlTimestamp = strtotime($ghlCreated);
                        echo "   Fecha original GHL: " . date('Y-m-d H:i:s', $ghlTimestamp) . "\n";
                        
                        // Comparar fechas
                        $diff = abs($sub['started_at'] - $ghlTimestamp);
                        if ($diff < 86400) { // Menos de 24 horas de diferencia
                            echo "   ✅ Fecha correcta (diferencia: " . round($diff / 3600, 2) . " horas)\n";
                        } else {
                            echo "   ⚠️  FECHA INCORRECTA (diferencia: " . round($diff / 86400, 2) . " días)\n";
                            echo "   🔧 Necesita corrección: debería ser " . date('Y-m-d H:i:s', $ghlTimestamp) . "\n";
                        }
                    }
                }
            } catch (Exception $e) {
                echo "   ⚠️  No se pudo verificar fecha en GHL: " . $e->getMessage() . "\n";
            }
            
            echo "\n";
        }
    } else {
        echo "   ❌ No se encontraron suscripciones para este cliente\n\n";
    }
    
    echo "\n";
}

echo "=================================================\n";
echo "RESUMEN\n";
echo "=================================================\n";
echo "Total usuarios verificados: " . count($testUsers) . "\n";
echo "\n";
