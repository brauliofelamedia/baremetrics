<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baremetricsService = app(\App\Services\BaremetricsService::class);
$ghlService = app(\App\Services\GoHighLevelService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== VERIFICACIÓN DE CAMPO COUPON ===\n\n";

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

echo "========================================\n\n";

foreach ($testUsers as $index => $customer) {
    $num = $index + 1;
    echo "#{$num}. {$customer['email']}\n";
    echo str_repeat("-", 60) . "\n";
    
    // Obtener coupon desde GHL
    try {
        $ghlContact = $ghlService->getContacts($customer['email']);
        if ($ghlContact && isset($ghlContact['contacts']) && !empty($ghlContact['contacts'])) {
            $contact = $ghlContact['contacts'][0];
            $ghlSubscription = $ghlService->getSubscriptionStatusByContact($contact['id']);
            $couponCodeGHL = $ghlSubscription['couponCode'] ?? null;
            
            echo "📦 GHL:\n";
            echo "   Coupon Code: " . ($couponCodeGHL ?: '❌ Sin cupón') . "\n";
        } else {
            $couponCodeGHL = null;
            echo "📦 GHL: ⚠️  No se pudo obtener datos\n";
        }
    } catch (Exception $e) {
        $couponCodeGHL = null;
        echo "📦 GHL: ❌ Error - " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Verificar coupon en Baremetrics
    $couponCodeBaremetrics = null;
    $hasCouponField = false;
    
    // Los custom fields están en 'properties', no en 'attributes'
    if (isset($customer['properties']) && is_array($customer['properties'])) {
        echo "📋 BAREMETRICS Properties:\n";
        
        // Mapeo de field_id a nombres
        $fieldMapping = [
            '727708655' => 'Relationship Status',
            '727708792' => 'Community Location',
            '727706634' => 'Country',
            '727707546' => 'Engagement Score',
            '727708656' => 'Has Kids',
            '727707002' => 'State',
            '727709283' => 'Location',
            '727708657' => 'Zodiac Sign',
            '750414465' => 'Subscriptions',
            '750342442' => 'Coupon Code',
            '844539743' => 'GHL: Migrate GHL'
        ];
        
        foreach ($customer['properties'] as $prop) {
            $fieldId = $prop['field_id'] ?? null;
            $value = $prop['value'] ?? 'N/A';
            $fieldName = $fieldMapping[$fieldId] ?? "Unknown ({$fieldId})";
            
            echo "   • {$fieldName}: {$value}\n";
            
            // Si es el campo de coupon (750342442)
            if ($fieldId === '750342442') {
                $hasCouponField = true;
                $couponCodeBaremetrics = $value;
            }
        }
        
        if (!$hasCouponField) {
            echo "   ⚠️  No se encontró campo de coupon (field_id: 750342442)\n";
        }
    } else {
        echo "📋 BAREMETRICS: ⚠️  No se encontraron properties\n";
    }
    
    echo "\n";
    
    // Comparación
    echo "🔍 COMPARACIÓN:\n";
    if ($couponCodeGHL && $couponCodeBaremetrics) {
        if ($couponCodeGHL === $couponCodeBaremetrics) {
            echo "   ✅ CORRECTO: Cupones coinciden\n";
        } else {
            echo "   ❌ ERROR: Cupones NO coinciden\n";
            echo "      GHL:        {$couponCodeGHL}\n";
            echo "      Baremetrics: {$couponCodeBaremetrics}\n";
        }
    } elseif ($couponCodeGHL && !$couponCodeBaremetrics) {
        echo "   ⚠️  FALTA: GHL tiene cupón pero Baremetrics NO\n";
        echo "      GHL: {$couponCodeGHL}\n";
    } elseif (!$couponCodeGHL && $couponCodeBaremetrics) {
        echo "   ⚠️  EXTRA: Baremetrics tiene cupón pero GHL NO\n";
        echo "      Baremetrics: {$couponCodeBaremetrics}\n";
    } else {
        echo "   ℹ️  Ninguno tiene cupón (normal si no se usó cupón)\n";
    }
    
    echo "\n\n";
}

echo "========================================\n";
