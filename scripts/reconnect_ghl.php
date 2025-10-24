<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "========================================\n";
echo "RECONECTAR GHL CON NUEVOS SCOPES\n";
echo "========================================\n\n";

// 1. Obtener la configuración actual
$config = \App\Models\Configuration::first();

if (!$config) {
    die("❌ No se encontró configuración de API\n");
}

echo "📋 Configuración actual:\n";
echo "   Location ID: {$config->ghl_location_id}\n";
echo "   Token expira: {$config->ghl_token_expires_at}\n\n";

// 2. Limpiar el token actual
echo "🧹 Limpiando token actual...\n";

$config->ghl_token = null;
$config->ghl_refresh_token = null;
$config->ghl_token_expires_at = null;
$config->save();

echo "✅ Token limpiado\n\n";

// 3. Generar URL de autorización
$clientId = $config->ghl_client_id ?: config('services.gohighlevel.client_id');
$redirectUri = config('services.gohighlevel.redirect_uri');

$scopes = [
    'contacts.readonly',
    'contacts.write',
    'locations/customFields.readonly',
    'businesses.readonly',
    'businesses.write',
];

$authUrl = "https://marketplace.gohighlevel.com/oauth/chooselocation?" . http_build_query([
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'client_id' => $clientId,
    'scope' => implode(' ', $scopes),
]);

echo "========================================\n";
echo "PASOS PARA RECONECTAR:\n";
echo "========================================\n\n";

echo "1. Abre esta URL en tu navegador:\n\n";
echo "   $authUrl\n\n";

echo "2. Autoriza la aplicación seleccionando tu location\n\n";

echo "3. Serás redirigido a tu callback URL y el token se guardará automáticamente\n\n";

echo "4. Una vez completado, ejecuta este comando para verificar:\n";
echo "   php test_ghl_update.php\n\n";

echo "========================================\n";
echo "INFORMACIÓN TÉCNICA\n";
echo "========================================\n\n";

echo "Client ID: $clientId\n";
echo "Redirect URI: $redirectUri\n";
echo "Scopes solicitados:\n";
foreach ($scopes as $scope) {
    echo "   - $scope\n";
}

echo "\n⚠️  IMPORTANTE: Los scopes deben estar habilitados en tu aplicación de GHL\n";
echo "   Verifica en: https://marketplace.gohighlevel.com/apps/my-apps\n\n";
