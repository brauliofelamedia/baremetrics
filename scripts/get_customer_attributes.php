<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$baremetricsApiKey = env('BAREMETRICS_API_KEY');
$baseUrl = 'https://api.baremetrics.com/v1';

// Obtener el source_id de Stripe
$sourceUrl = $baseUrl . '/sources';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sourceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $baremetricsApiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
curl_close($ch);

$sources = json_decode($response, true);
$stripeSourceId = null;

if (isset($sources['sources'])) {
    foreach ($sources['sources'] as $source) {
        if ($source['provider'] === 'stripe') {
            $stripeSourceId = $source['id'];
            break;
        }
    }
}

if (!$stripeSourceId) {
    die("No se encontró source de Stripe\n");
}

echo "Source ID de Stripe: $stripeSourceId\n\n";

// Buscar un customer para ver sus atributos
$customersUrl = $baseUrl . '/' . $stripeSourceId . '/customers?per_page=1';
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

if (isset($data['customers'][0])) {
    $customer = $data['customers'][0];
    echo "Customer ID: " . $customer['oid'] . "\n";
    echo "Customer Email: " . ($customer['email'] ?? 'N/A') . "\n\n";
    
    // Obtener atributos del customer
    $attributesUrl = $baseUrl . '/attributes/' . $customer['oid'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $attributesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $baremetricsApiKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Atributos del customer:\n";
    echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n";
}
