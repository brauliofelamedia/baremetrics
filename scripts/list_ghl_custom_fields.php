<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CUSTOM FIELDS DISPONIBLES EN GHL ===\n\n";

$ghlService = new \App\Services\GoHighLevelService();

try {
    // Obtener todos los custom fields de GHL
    $customFields = $ghlService->getCustomFields();
    
    if ($customFields && isset($customFields['customFields'])) {
        $fields = $customFields['customFields'];
        
        echo "Total de custom fields encontrados: " . count($fields) . "\n\n";
        echo str_repeat("=", 80) . "\n";
        
        // Buscar campos relacionados con cancelación
        $cancelFields = [];
        
        foreach ($fields as $field) {
            $id = $field['id'] ?? 'N/A';
            $name = $field['name'] ?? 'N/A';
            $dataType = $field['dataType'] ?? 'N/A';
            
            echo sprintf("%-30s | %-35s | %s\n", $id, $name, $dataType);
            
            // Buscar campos relacionados con cancelación
            $nameLower = strtolower($name);
            if (
                strpos($nameLower, 'cancel') !== false ||
                strpos($nameLower, 'reason') !== false ||
                strpos($nameLower, 'motivo') !== false ||
                strpos($nameLower, 'churn') !== false
            ) {
                $cancelFields[] = $field;
            }
        }
        
        echo str_repeat("=", 80) . "\n\n";
        
        if (!empty($cancelFields)) {
            echo "✅ CAMPOS RELACIONADOS CON CANCELACIÓN ENCONTRADOS:\n";
            echo str_repeat("=", 80) . "\n";
            foreach ($cancelFields as $field) {
                echo "• ID: " . ($field['id'] ?? 'N/A') . "\n";
                echo "  Nombre: " . ($field['name'] ?? 'N/A') . "\n";
                echo "  Tipo: " . ($field['dataType'] ?? 'N/A') . "\n";
                echo "  Posición: " . ($field['position'] ?? 'N/A') . "\n\n";
            }
        } else {
            echo "⚠️ NO SE ENCONTRARON CAMPOS RELACIONADOS CON CANCELACIÓN\n\n";
            echo "📝 RECOMENDACIÓN:\n";
            echo "Necesitas crear un nuevo custom field en GHL para el motivo de cancelación.\n\n";
            echo "Sugerencias de nombres:\n";
            echo "  1. 'Cancellation Reason' (en inglés)\n";
            echo "  2. 'Motivo de Cancelación' (en español)\n";
            echo "  3. 'Churn Reason'\n";
            echo "  4. 'Exit Reason'\n\n";
            echo "Tipo de campo recomendado: TEXT o TEXTAREA\n\n";
            echo "🔗 Para crear el campo:\n";
            echo "1. Ve a GHL → Settings → Custom Fields\n";
            echo "2. Crea un nuevo campo de tipo TEXT o TEXTAREA\n";
            echo "3. Asigna el nombre sugerido\n";
            echo "4. Guarda el ID del campo para usar en el código\n\n";
        }
        
        echo "=== CAMPOS MÁS USADOS EN TU INTEGRACIÓN ACTUAL ===\n";
        echo str_repeat("=", 80) . "\n";
        
        $knownFields = [
            '1fFJJsONHbRMQJCstvg1' => 'Relationship Status',
            'q3BHfdxzT2uKfNO3icXG' => 'Community Location',
            'j175N7HO84AnJycpUb9D' => 'Engagement Score',
            'xy0zfzMRFpOdXYJkHS2c' => 'Has Kids',
            'JuiCbkHWsSc3iKfmOBpo' => 'Zodiac Sign'
        ];
        
        foreach ($knownFields as $fieldId => $fieldName) {
            // Buscar si existe en los campos obtenidos
            $exists = false;
            foreach ($fields as $field) {
                if (($field['id'] ?? '') === $fieldId) {
                    $exists = true;
                    echo "✅ {$fieldName} ({$fieldId}) - Tipo: " . ($field['dataType'] ?? 'N/A') . "\n";
                    break;
                }
            }
            if (!$exists) {
                echo "❌ {$fieldName} ({$fieldId}) - NO ENCONTRADO\n";
            }
        }
        
    } else {
        echo "❌ No se pudieron obtener los custom fields de GHL\n";
        echo "Respuesta: " . json_encode($customFields, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error al obtener custom fields: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SIGUIENTE PASO:\n";
echo str_repeat("=", 80) . "\n";
echo "Si NO existe un campo para cancelación, tienes dos opciones:\n\n";
echo "OPCIÓN 1 (RECOMENDADA): Crear un nuevo custom field en GHL\n";
echo "  • Ir a GHL Settings → Custom Fields\n";
echo "  • Crear campo de tipo TEXTAREA\n";
echo "  • Nombre: 'Cancellation Reason' o 'Motivo de Cancelación'\n";
echo "  • Copiar el ID generado\n\n";
echo "OPCIÓN 2: Usar un campo existente que no esté en uso\n";
echo "  • Revisar la lista arriba\n";
echo "  • Elegir un campo que no tenga datos importantes\n";
echo "  • Usar su ID en el código\n\n";
echo "Después, ejecuta este script nuevamente para verificar el campo.\n";
