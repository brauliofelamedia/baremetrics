<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Inicializar el servicio Baremetrics
$baremetrics = new \App\Services\BaremetricsService();

// Obtener todos los campos personalizados disponibles
$customFields = $baremetrics->getCustomFields();

echo "=== Campos personalizados disponibles en Baremetrics ===\n";

if (!$customFields || !isset($customFields['fields'])) {
    echo "No se pudieron obtener los campos personalizados o no hay ninguno definido.\n";
    exit(1);
}

// Imprimir todos los campos para referencia
foreach ($customFields['fields'] as $field) {
    echo "ID: {$field['id']}, Nombre: {$field['name']}\n";
}

// Buscar específicamente campos relacionados con suscripciones
echo "\n=== Campos posiblemente relacionados con 'Subscriptions' ===\n";
$found = false;

foreach ($customFields['fields'] as $field) {
    $name = strtolower($field['name']);
    if (strpos($name, 'subscription') !== false || 
        strpos($name, 'suscripción') !== false || 
        strpos($name, 'member') !== false ||
        strpos($name, 'plan') !== false) {
        echo "ID: {$field['id']}, Nombre: {$field['name']}\n";
        $found = true;
    }
}

if (!$found) {
    echo "No se encontraron campos relacionados con suscripciones.\n";
}

echo "\n=== Instrucciones para actualizar el campo 'subscriptions' ===\n";
echo "1. Identifica en la lista anterior el ID del campo que corresponde a 'Subscriptions'.\n";
echo "2. Actualiza el método updateCustomerAttributes en BaremetricsService.php con el ID correcto.\n";
echo "3. Reemplaza '727712345' con el ID correcto en el mapeo de campos.\n";