<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Inicializar el servicio Baremetrics
$baremetrics = new \App\Services\BaremetricsService();

// El ID del cliente que queremos verificar
$customerId = "cus_Su3S9c9HzNihl0";

// Realizar llamada directa para obtener los campos personalizados
$url = $baremetrics->getBaseUrl() . '/attributes/fields';
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $baremetrics->getApiKey(),
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
])->get($url);

if ($response->successful()) {
    $fields = $response->json();
    echo "=== Campos personalizados disponibles en Baremetrics ===\n";
    if (isset($fields['fields']) && is_array($fields['fields'])) {
        foreach ($fields['fields'] as $field) {
            echo "ID: {$field['id']}, Nombre: {$field['name']}\n";
        }
    } else {
        echo "No se encontraron campos personalizados\n";
    }
} else {
    echo "Error al obtener campos personalizados: " . $response->status() . "\n";
    echo $response->body() . "\n";
}

// Ahora intentemos obtener los atributos específicos para el cliente
$url = $baremetrics->getBaseUrl() . '/attributes?customer_oid=' . $customerId;
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer ' . $baremetrics->getApiKey(),
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
])->get($url);

if ($response->successful()) {
    $attributes = $response->json();
    echo "\n=== Atributos del cliente {$customerId} ===\n";
    if (isset($attributes['attributes']) && is_array($attributes['attributes'])) {
        foreach ($attributes['attributes'] as $attribute) {
            echo "Field ID: {$attribute['field_id']}, Valor: {$attribute['value']}\n";
        }
    } else {
        echo "No se encontraron atributos para este cliente\n";
    }
} else {
    echo "Error al obtener atributos del cliente: " . $response->status() . "\n";
    echo $response->body() . "\n";
}

// Análisis específico para encontrar el campo de membership_status
echo "\n=== Buscando campo de membership_status ===\n";
$membershipFieldId = '727710001'; // El ID que estamos cuestionando
echo "El ID que se está usando actualmente es: {$membershipFieldId}\n";

// Verifiquemos si este ID existe en los campos personalizados
$found = false;
if (isset($fields['fields']) && is_array($fields['fields'])) {
    foreach ($fields['fields'] as $field) {
        if ($field['id'] == $membershipFieldId) {
            echo "¡ENCONTRADO! ID: {$field['id']}, Nombre: {$field['name']}\n";
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "El ID {$membershipFieldId} NO se encontró en los campos personalizados disponibles.\n";
        
        // Buscar campos con nombres similares a "membership" o "status"
        echo "\nPosibles campos relacionados con 'membership' o 'status':\n";
        $related = false;
        foreach ($fields['fields'] as $field) {
            if (stripos($field['name'], 'membership') !== false || 
                stripos($field['name'], 'status') !== false ||
                stripos($field['name'], 'member') !== false) {
                echo "ID: {$field['id']}, Nombre: {$field['name']}\n";
                $related = true;
            }
        }
        
        if (!$related) {
            echo "No se encontraron campos relacionados con 'membership' o 'status'.\n";
        }
    }
}

// Verificar si el campo 668513684 (que aparece en los datos del cliente) podría ser el campo de membership_status
echo "\nEl cliente tiene un campo con ID 668513684 y valor 'cancellation_requested'\n";
echo "Este podría ser un posible candidato para el campo de membership_status.\n";