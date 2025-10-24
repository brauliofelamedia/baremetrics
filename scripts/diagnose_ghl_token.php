<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "DIAGNÓSTICO DE TOKEN GHL\n";
echo "========================================\n\n";

// 1. Obtener el token actual
$tokenData = \App\Models\GoHighLevelToken::first();

if (!$tokenData) {
    die("❌ No se encontró token de GHL en la base de datos\n");
}

echo "✅ Token encontrado en BD:\n";
echo "   Location ID: {$tokenData->location_id}\n";
echo "   Expira: {$tokenData->expires_at}\n";
echo "   Estado: " . ($tokenData->expires_at > now() ? "✅ Válido" : "⚠️ Expirado") . "\n\n";

// 2. Probar el token con una operación simple (GET)
echo "2. Probando token con operación GET (listar contactos)...\n";

try {
    $ghlService = app(\App\Services\GoHighLevelService::class);
    $contacts = $ghlService->getContacts('braulio@felamedia.com');
    
    if (!empty($contacts['contacts'])) {
        echo "✅ Token funciona para operaciones GET\n";
        echo "   Contacto encontrado: {$contacts['contacts'][0]['id']}\n";
        $contactId = $contacts['contacts'][0]['id'];
        echo "\n";
    } else {
        echo "⚠️  No se encontraron contactos\n\n";
        $contactId = null;
    }
} catch (\Exception $e) {
    echo "❌ Error en operación GET: " . $e->getMessage() . "\n\n";
    $contactId = null;
}

// 3. Probar actualización de custom field
if ($contactId) {
    echo "3. Probando actualización de custom field...\n";
    
    try {
        $updateData = [
            'customField' => [
                'UhyA0ol6XoETLRA5jsZa' => 'Test de permisos - ' . date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $ghlService->updateContact($contactId, $updateData);
        
        if ($result) {
            echo "✅ Token tiene permisos para actualizar custom fields\n";
            echo "   Actualización exitosa\n\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error actualizando custom field: " . $e->getMessage() . "\n";
        
        if (strpos($e->getMessage(), 'not authorized for this scope') !== false) {
            echo "\n";
            echo "🔴 PROBLEMA IDENTIFICADO:\n";
            echo "   El token NO tiene los permisos (scopes) necesarios para actualizar custom fields.\n\n";
            echo "SOLUCIÓN:\n";
            echo "1. Ve a la configuración de tu aplicación en GHL\n";
            echo "2. Asegúrate de que estos scopes estén habilitados:\n";
            echo "   - contacts.write (requerido para actualizar contactos)\n";
            echo "   - contacts.readonly (para leer contactos)\n";
            echo "3. Reconecta la aplicación para obtener un nuevo token con los permisos correctos\n";
            echo "4. URL de reautorización: https://baremetrics.local/gohighlevel/callback\n\n";
        }
    }
}

// 4. Mostrar información de scopes requeridos
echo "\n========================================\n";
echo "SCOPES REQUERIDOS PARA GHL\n";
echo "========================================\n\n";

echo "Para que la integración funcione completamente, necesitas:\n\n";
echo "✅ contacts.readonly - Para buscar contactos por email\n";
echo "✅ contacts.write - Para actualizar custom fields en contactos\n";
echo "✅ locations.readonly - Para acceder a información de la ubicación\n\n";

echo "CÓMO ACTUALIZAR LOS PERMISOS:\n";
echo "1. Ve a: https://marketplace.gohighlevel.com/apps/my-apps\n";
echo "2. Selecciona tu aplicación\n";
echo "3. Ve a 'OAuth Scopes'\n";
echo "4. Asegúrate de que los scopes anteriores estén habilitados\n";
echo "5. Guarda los cambios\n";
echo "6. Reconecta la aplicación desde: https://baremetrics.local/gohighlevel/auth\n\n";
