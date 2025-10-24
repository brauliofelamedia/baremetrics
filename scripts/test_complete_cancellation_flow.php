<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar Stripe
\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

$email = 'braulio@felamedia.com';

echo "========================================\n";
echo "TEST DE CANCELACIÓN COMPLETA\n";
echo "========================================\n\n";

// 1. Buscar el customer en Stripe
echo "1. Buscando customer en Stripe...\n";
try {
    $customers = \Stripe\Customer::all([
        'email' => $email,
        'limit' => 1
    ]);
    
    if (empty($customers->data)) {
        die("❌ No se encontró customer con email: $email\n");
    }
    
    $customer = $customers->data[0];
    $customerId = $customer->id;
    
    echo "✅ Customer encontrado:\n";
    echo "   ID: $customerId\n";
    echo "   Email: {$customer->email}\n";
    echo "   Nombre: {$customer->name}\n\n";
    
} catch (\Exception $e) {
    die("❌ Error buscando customer: " . $e->getMessage() . "\n");
}

// 2. Verificar suscripciones activas
echo "2. Verificando suscripciones activas...\n";
try {
    $subscriptions = \Stripe\Subscription::all([
        'customer' => $customerId,
        'status' => 'active',
        'limit' => 10
    ]);
    
    if (empty($subscriptions->data)) {
        echo "⚠️  No hay suscripciones activas\n\n";
    } else {
        echo "✅ Suscripciones activas: " . count($subscriptions->data) . "\n";
        foreach ($subscriptions->data as $sub) {
            echo "   - {$sub->id} (Status: {$sub->status})\n";
        }
        echo "\n";
    }
} catch (\Exception $e) {
    echo "⚠️  Error verificando suscripciones: " . $e->getMessage() . "\n\n";
}

// 3. Simular el survey
echo "3. Simulando envío de survey de cancelación...\n";
$testData = [
    'customer_id' => $customerId,
    'email' => $email,
    'reason' => 'Prueba de integración completa - Testing all systems',
    'additional_comments' => 'Este es un comentario de prueba para verificar que todos los sistemas reciban la información correctamente. Timestamp: ' . date('Y-m-d H:i:s')
];

echo "   Datos del survey:\n";
echo "   - Customer ID: {$testData['customer_id']}\n";
echo "   - Email: {$testData['email']}\n";
echo "   - Reason: {$testData['reason']}\n";
echo "   - Comments: {$testData['additional_comments']}\n\n";

// 4. Hacer la petición POST al endpoint
echo "4. Enviando petición al endpoint de cancelación...\n";

$url = 'https://baremetrics.local/gohighlevel/cancellation/survey/' . $customerId;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'reason' => $testData['reason'],
    'additional_comments' => $testData['additional_comments']
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
echo "   Response: $response\n\n";

if ($httpCode !== 200) {
    echo "⚠️  La petición no fue exitosa\n\n";
} else {
    echo "✅ Petición enviada exitosamente\n\n";
}

// 5. Verificar que se guardó en la base de datos
echo "5. Verificando registro en base de datos...\n";
try {
    $survey = \App\Models\CancellationSurvey::where('customer_id', $customerId)
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($survey) {
        echo "✅ Survey encontrado en BD:\n";
        echo "   - ID: {$survey->id}\n";
        echo "   - Customer ID: {$survey->customer_id}\n";
        echo "   - Email: {$survey->email}\n";
        echo "   - Reason: {$survey->reason}\n";
        echo "   - Comments: {$survey->additional_comments}\n";
        echo "   - Fecha: {$survey->created_at}\n\n";
    } else {
        echo "❌ No se encontró el survey en la BD\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Error verificando BD: " . $e->getMessage() . "\n\n";
}

// 6. Verificar logs
echo "6. Últimos logs relacionados (últimas 20 líneas)...\n";
echo "   Para ver logs completos ejecuta: tail -f storage/logs/laravel.log | grep -i 'cancel\|baremetrics\|ghl'\n\n";

try {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $relevantLines = array_slice($lines, -30);
        
        foreach ($relevantLines as $line) {
            if (stripos($line, 'cancel') !== false || 
                stripos($line, 'baremetrics') !== false || 
                stripos($line, 'ghl') !== false ||
                stripos($line, $customerId) !== false) {
                echo "   " . trim($line) . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "⚠️  No se pudieron leer los logs\n";
}

echo "\n========================================\n";
echo "TEST COMPLETADO\n";
echo "========================================\n\n";

echo "SIGUIENTE PASO:\n";
echo "1. Verificar en Baremetrics que los campos 'GHL: Cancellation Reason' y 'GHL: Cancellation Comments' se hayan actualizado\n";
echo "2. Verificar en GHL que los campos 'Motivo de cancelacion' y 'Comentarios de cancelacion' se hayan actualizado\n";
echo "3. Revisar los logs completos: tail -f storage/logs/laravel.log\n";
