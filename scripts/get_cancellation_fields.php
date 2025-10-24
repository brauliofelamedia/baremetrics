<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar para producción
config(['services.baremetrics.environment' => 'production']);

// Inicializar el servicio Baremetrics
$baremetrics = new \App\Services\BaremetricsService();
$baremetrics->reinitializeConfiguration();

echo "=== BUSCAR CAMPOS DE CANCELACIÓN EN BAREMETRICS ===\n\n";
echo "Configuración:\n";
echo "Base URL: " . $baremetrics->getBaseUrl() . "\n";
echo "API Key: " . substr($baremetrics->getApiKey(), 0, 20) . "...\n";
echo "\n";

// Obtener todos los campos personalizados
$url = $baremetrics->getBaseUrl() . '/attributes/fields';
echo "Consultando: $url\n\n";
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $baremetrics->getApiKey(),
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
])->get($url);

if ($response->successful()) {
    $fields = $response->json();
    
    echo "Todos los campos personalizados disponibles:\n";
    echo str_repeat("=", 80) . "\n";
    
    if (isset($fields['fields']) && is_array($fields['fields'])) {
        foreach ($fields['fields'] as $field) {
            echo sprintf("ID: %-12s | Nombre: %s\n", $field['id'], $field['name']);
        }
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Buscando campos relacionados con cancelación:\n";
        echo str_repeat("=", 80) . "\n";
        
        $cancelFields = [];
        foreach ($fields['fields'] as $field) {
            $name = strtolower($field['name']);
            if (
                strpos($name, 'active subscription') !== false ||
                strpos($name, 'canceled') !== false ||
                strpos($name, 'cancelled') !== false ||
                strpos($name, 'cancellation') !== false ||
                strpos($name, 'cancel reason') !== false
            ) {
                $cancelFields[] = $field;
                echo sprintf("✓ ID: %-12s | Nombre: %s\n", $field['id'], $field['name']);
            }
        }
        
        if (empty($cancelFields)) {
            echo "⚠ No se encontraron campos específicos de cancelación.\n";
            echo "Los campos podrían tener nombres diferentes. Revisa la lista completa arriba.\n";
        }
        
    } else {
        echo "No se encontraron campos personalizados\n";
    }
} else {
    echo "Error al obtener campos personalizados: " . $response->status() . "\n";
    echo $response->body() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Campos que necesitamos mapear:\n";
echo str_repeat("=", 80) . "\n";
echo "1. Active Subscription? (campo booleano)\n";
echo "2. Canceled? (campo booleano)\n";
echo "3. Cancellation Reason (campo de texto)\n";
echo "\nBusca estos nombres en la lista de arriba y anota sus IDs.\n";
