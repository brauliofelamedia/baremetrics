<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Inicializar el servicio Baremetrics
$baremetrics = new \App\Services\BaremetricsService();

// Obtener las fuentes
$sources = $baremetrics->getSources();
echo "=== Fuentes disponibles ===\n";
print_r($sources);
echo "\n\n";

// Buscar cliente por email
$customerId = "cus_Su3S9c9HzNihl0";
$email = "jorge@felamedia.com";

// Verificar cada fuente para encontrar el cliente
if (isset($sources['sources']) && is_array($sources['sources'])) {
    foreach ($sources['sources'] as $source) {
        $sourceId = $source['id'];
        echo "Buscando en la fuente: {$sourceId}\n";
        
        // Buscar por email primero
        $customers = $baremetrics->getCustomers($sourceId, $email);
        if (!empty($customers['customers'])) {
            echo "Cliente encontrado por email en fuente {$sourceId}:\n";
            print_r($customers);
            
            // Verificar los atributos personalizados del cliente
            if (isset($customers['customers'][0]['oid'])) {
                $customerOid = $customers['customers'][0]['oid'];
                echo "Atributos del cliente con OID {$customerOid}:\n";
                
                // Aquí deberíamos intentar obtener los atributos personalizados
                // pero necesitamos un método en BaremetricsService para hacer esto
                echo "Para obtener atributos personalizados, necesitamos implementar un método en BaremetricsService\n";
            }
            
            break;
        }
    }
}

// Si no se encontró por email, intentemos con el ID directamente
echo "\nBuscando cliente con ID: {$customerId}\n";
// Aquí deberíamos intentar obtener el cliente por ID
// pero necesitamos un método en BaremetricsService para hacer esto

echo "\nPara obtener información completa de los atributos personalizados, necesitamos:\n";
echo "1. Verificar la documentación de Baremetrics API\n";
echo "2. Implementar un método getCustomerAttributes en BaremetricsService\n";
echo "3. Usar ese método para obtener los IDs correctos de los campos\n";