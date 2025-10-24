<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Usar el mismo método que BaremetricsService
$baremetricsApiKey = config('services.baremetrics.live_key') ?: config('services.baremetrics.sandbox_key');
$baseUrl = 'https://api.baremetrics.com/v1';

echo "Buscando custom fields en Baremetrics...\n\n";

// Primero obtener el source_id de Stripe
$sourceUrl = $baseUrl . '/sources';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sourceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $baremetricsApiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== SOURCES ===\n";
echo "HTTP Code: $httpCode\n";
$sources = json_decode($response, true);

if (isset($sources['sources'])) {
    foreach ($sources['sources'] as $source) {
        echo "Source: {$source['provider']} (ID: {$source['id']})\n";
    }
}

// Intentar obtener campos custom desde /customer_fields
echo "\n=== INTENTANDO /customer_fields ===\n";
$fieldsUrl = $baseUrl . '/customer_fields';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fieldsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $baremetricsApiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Respuesta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "Error. Respuesta:\n";
    echo substr($response, 0, 500) . "...\n\n";
}

// Intentar obtener campos custom desde /attributes/fields
echo "\n=== INTENTANDO /attributes/fields ===\n";
$fieldsUrl = $baseUrl . '/attributes/fields';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fieldsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $baremetricsApiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "Respuesta:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    // Mostrar campos encontrados
    if (isset($data['fields'])) {
        echo "\n=== CUSTOM FIELDS ENCONTRADOS ===\n";
        foreach ($data['fields'] as $field) {
            printf("%-15s | %-50s | %s\n", 
                $field['id'] ?? 'N/A',
                $field['name'] ?? 'N/A',
                $field['type'] ?? 'N/A'
            );
        }
    }
} else {
    echo "Error. Respuesta:\n";
    echo substr($response, 0, 500) . "...\n\n";
}

// Buscar un customer y obtener sus atributos para ver los field IDs en uso
echo "\n=== BUSCANDO CUSTOMER CON ATRIBUTOS ===\n";
$stripeSourceId = null;
if (isset($sources['sources'])) {
    foreach ($sources['sources'] as $source) {
        if ($source['provider'] === 'stripe') {
            $stripeSourceId = $source['id'];
            break;
        }
    }
}

if ($stripeSourceId) {
    // Buscar customers
    $customersUrl = $baseUrl . '/' . $stripeSourceId . '/customers?per_page=5';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $customersUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $baremetricsApiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['customers'])) {
        foreach ($data['customers'] as $customer) {
            if (!empty($customer['attributes'])) {
                echo "\nCustomer: " . ($customer['email'] ?? $customer['oid']) . "\n";
                echo "Atributos:\n";
                foreach ($customer['attributes'] as $attr) {
                    printf("  Field ID: %-15s | Name: %-40s | Value: %s\n", 
                        $attr['field_id'] ?? 'N/A',
                        $attr['field_name'] ?? 'N/A',
                        $attr['value'] ?? 'N/A'
                    );
                }
                break; // Solo mostrar el primero con atributos
            }
        }
    }
}
