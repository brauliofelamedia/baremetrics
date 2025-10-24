<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar Stripe
\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

$email = 'braulio@felamedia.com';

echo "========================================\n";
echo "TEST DE ACTUALIZACIÓN GHL\n";
echo "========================================\n\n";

// 1. Buscar el contacto en GHL
echo "1. Buscando contacto en GHL...\n";
try {
    $ghlService = app(\App\Services\GoHighLevelService::class);
    $ghlContact = $ghlService->getContactsByExactEmail($email);
    
    if (empty($ghlContact['contacts'])) {
        // Intentar con búsqueda contains
        $ghlContact = $ghlService->getContacts($email);
    }
    
    if (empty($ghlContact['contacts'])) {
        die("❌ No se encontró contacto en GHL con email: $email\n");
    }
    
    $contact = $ghlContact['contacts'][0];
    $contactId = $contact['id'];
    
    echo "✅ Contacto encontrado:\n";
    echo "   ID: $contactId\n";
    echo "   Email: {$contact['email']}\n";
    echo "   Nombre: " . ($contact['firstName'] ?? '') . " " . ($contact['lastName'] ?? '') . "\n\n";
    
} catch (\Exception $e) {
    die("❌ Error buscando contacto: " . $e->getMessage() . "\n");
}

// 2. Preparar datos de prueba
$testReason = "Cambiaron mis prioridades. No era lo que necesitaba en esta etapa de mi negocio o vida.";
$testComments = "Comentarios de prueba.";

echo "2. Preparando datos de prueba...\n";
echo "   Motivo: $testReason\n";
echo "   Comentarios: $testComments\n\n";

// 3. Actualizar custom fields en GHL
echo "3. Actualizando custom fields en GHL...\n";

try {
    $customFields = [
        'UhyA0ol6XoETLRA5jsZa' => $testReason,  // Campo "Motivo de cancelacion"
        'zYi50QSDZC6eGqoRH8Zm' => $testComments,  // Campo "Comentarios de cancelacion"
    ];
    
    echo "   Datos a enviar:\n";
    echo "   - Campo UhyA0ol6XoETLRA5jsZa (Motivo): $testReason\n";
    echo "   - Campo zYi50QSDZC6eGqoRH8Zm (Comentarios): $testComments\n\n";
    
    $result = $ghlService->updateContactCustomFields($contactId, $customFields);
    
    if ($result) {
        echo "✅ Custom fields actualizados exitosamente en GHL\n\n";
    } else {
        echo "❌ No se pudieron actualizar los custom fields\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ Error actualizando custom fields: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'not authorized for this scope') !== false) {
        echo "🔴 El token sigue sin tener los permisos correctos.\n";
        echo "   Verifica que los scopes incluyan 'contacts.write'\n\n";
    }
    
    exit(1);
}

// 4. Verificar la actualización leyendo el contacto nuevamente
echo "4. Verificando actualización...\n";

try {
    $updatedContact = $ghlService->getContactsByExactEmail($email);
    
    if (!empty($updatedContact['contacts'])) {
        $contact = $updatedContact['contacts'][0];
        
        echo "✅ Contacto actualizado verificado:\n";
        
        if (isset($contact['customField'])) {
            $customFields = $contact['customField'];
            
            if (isset($customFields['UhyA0ol6XoETLRA5jsZa'])) {
                echo "   ✅ Motivo de cancelación: {$customFields['UhyA0ol6XoETLRA5jsZa']}\n";
            } else {
                echo "   ⚠️  Campo 'Motivo de cancelación' no encontrado\n";
            }
            
            if (isset($customFields['zYi50QSDZC6eGqoRH8Zm'])) {
                echo "   ✅ Comentarios de cancelación: {$customFields['zYi50QSDZC6eGqoRH8Zm']}\n";
            } else {
                echo "   ⚠️  Campo 'Comentarios de cancelación' no encontrado\n";
            }
        } else {
            echo "   ⚠️  No se encontraron custom fields en el contacto\n";
        }
    }
} catch (\Exception $e) {
    echo "⚠️  Error verificando actualización: " . $e->getMessage() . "\n";
}

echo "\n========================================\n";
echo "TEST COMPLETADO\n";
echo "========================================\n\n";

echo "RESUMEN:\n";
echo "- Contacto encontrado en GHL: ✅\n";
echo "- Custom fields actualizados: " . (isset($result) && $result ? "✅" : "❌") . "\n";
echo "- Verificación de campos: " . (isset($customFields['UhyA0ol6XoETLRA5jsZa']) ? "✅" : "⚠️") . "\n\n";

echo "Puedes verificar manualmente en GHL:\n";
echo "https://app.gohighlevel.com/location/4z3IHPMw9JB3Qkz8ttK8/contacts/detail/$contactId\n\n";
