<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Inicializar el servicio Baremetrics
$baremetrics = new \App\Services\BaremetricsService();

// El email del cliente que queremos buscar
$email = "jorge@felamedia.com";

// Obtener fuentes disponibles
$sources = $baremetrics->getSources();
echo "=== Buscando cliente por email: {$email} ===\n\n";

if (!$sources || !isset($sources['sources'])) {
    echo "No se pudieron obtener las fuentes de Baremetrics.\n";
    exit(1);
}

// Buscar el cliente en cada fuente
$clientFound = false;
foreach ($sources['sources'] as $source) {
    $sourceId = $source['id'];
    echo "Buscando en la fuente: {$sourceId} ({$source['provider']})\n";
    
    $customers = $baremetrics->getCustomers($sourceId, $email);
    
    if ($customers && isset($customers['customers']) && !empty($customers['customers'])) {
        $clientFound = true;
        echo "\n¡Cliente encontrado en la fuente {$sourceId}!\n";
        
        // Analizar cada cliente encontrado (puede haber más de uno con el mismo email)
        foreach ($customers['customers'] as $customer) {
            echo "\nDetalles del cliente:\n";
            echo "OID: {$customer['oid']}\n";
            echo "Nombre: {$customer['name']}\n";
            echo "Email: {$customer['email']}\n";
            echo "Teléfono: {$customer['phone_number']}\n";
            
            // Buscar campos personalizados
            echo "\nCampos personalizados:\n";
            if (isset($customer['properties']) && is_array($customer['properties'])) {
                echo "Total de propiedades: " . count($customer['properties']) . "\n\n";
                
                // Ordenar propiedades por field_id para facilitar la búsqueda
                usort($customer['properties'], function($a, $b) {
                    return $a['field_id'] <=> $b['field_id'];
                });
                
                foreach ($customer['properties'] as $property) {
                    $fieldId = $property['field_id'];
                    $value = $property['value'];
                    $updatedAt = date('Y-m-d H:i:s', $property['updated_at']);
                    
                    // Buscar específicamente campos que puedan estar relacionados con suscripciones
                    $highlight = "";
                    if (stripos($value, 'subscription') !== false || 
                        stripos($value, 'suscripción') !== false || 
                        stripos($value, 'member') !== false ||
                        stripos($value, 'status') !== false) {
                        $highlight = " *** POSIBLE CAMPO DE SUSCRIPCIÓN ***";
                    }
                    
                    echo "ID: {$fieldId}, Valor: {$value}, Actualizado: {$updatedAt}{$highlight}\n";
                }
            } else {
                echo "No se encontraron propiedades personalizadas para este cliente.\n";
            }
            
            // Buscar específicamente campos que puedan contener "subscription new"
            echo "\nBuscando campos que contengan 'Subscription new':\n";
            $subscriptionNewFound = false;
            if (isset($customer['properties']) && is_array($customer['properties'])) {
                foreach ($customer['properties'] as $property) {
                    $value = $property['value'];
                    if (stripos($value, 'subscription new') !== false) {
                        $fieldId = $property['field_id'];
                        $updatedAt = date('Y-m-d H:i:s', $property['updated_at']);
                        echo "¡ENCONTRADO! ID: {$fieldId}, Valor: {$value}, Actualizado: {$updatedAt}\n";
                        $subscriptionNewFound = true;
                    }
                }
                
                if (!$subscriptionNewFound) {
                    echo "No se encontraron campos con valor 'Subscription new'\n";
                }
            }
            
            echo "\n" . str_repeat('-', 80) . "\n";
        }
    }
}

if (!$clientFound) {
    echo "\nNo se encontró ningún cliente con el email {$email} en ninguna fuente.\n";
}

// Obtener también los campos personalizados disponibles
echo "\n=== Campos personalizados disponibles en Baremetrics ===\n";
$customFields = $baremetrics->getCustomFields();

if ($customFields && isset($customFields['fields'])) {
    foreach ($customFields['fields'] as $field) {
        $highlight = "";
        if (stripos($field['name'], 'subscription') !== false || 
            stripos($field['name'], 'suscripción') !== false || 
            stripos($field['name'], 'member') !== false) {
            $highlight = " *** POSIBLE CAMPO DE SUSCRIPCIÓN ***";
        }
        
        echo "ID: {$field['id']}, Nombre: {$field['name']}{$highlight}\n";
    }
} else {
    echo "No se pudieron obtener los campos personalizados o no hay ninguno definido.\n";
}

echo "\nPara actualizar el mapeo de campos en BaremetricsService.php:\n";
echo "1. Localiza el ID del campo 'Subscription new' arriba\n";
echo "2. Reemplaza el ID '727712345' con el ID correcto\n";
echo "3. Asegúrate que el nombre de la clave en el mapping sea 'subscriptions' o el que corresponda\n";