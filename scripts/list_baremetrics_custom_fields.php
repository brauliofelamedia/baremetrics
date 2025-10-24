<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar Stripe
\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

$baremetricsApiKey = env('BAREMETRICS_API_KEY');
$baseUrl = 'https://api.baremetrics.com/v1';

// Obtener todos los custom fields
$url = $baseUrl . '/fields';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $baremetricsApiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    echo "Custom Fields en Baremetrics:\n";
    echo str_repeat("=", 80) . "\n\n";
    
    if (isset($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $field) {
            printf("%-15s | %-40s | %s\n", 
                $field['id'] ?? 'N/A',
                $field['name'] ?? 'N/A',
                $field['type'] ?? 'N/A'
            );
            echo "  Nombre: " . ($field['name'] ?? 'N/A') . "\n";
            echo str_repeat("-", 80) . "\n";
        }
        
        echo "\nTotal de campos: " . count($data['fields']) . "\n";
    } else {
        echo "No se encontraron campos o formato inesperado.\n";
        echo "Respuesta: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Error al obtener campos. HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}
