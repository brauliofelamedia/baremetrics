<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\BaremetricsService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== Eliminando últimos 5 usuarios de prueba ===\n\n";

// Obtener suscripciones primero para tener los OIDs
$subscriptions = $service->getSubscriptions($sourceId);

$testUsers = [
    ['email' => 'yuvianat.holisticcoach@gmail.com', 'oid' => 'ghl_68e9412b1a9e0329602952'],
    ['email' => 'lizzleony@gmail.com', 'oid' => 'ghl_68e9412d4debe175881612'],
    ['email' => 'marisolkfitbyme@gmail.com', 'oid' => 'ghl_68e9412f2521f567959733'],
    ['email' => 'ninfa.cardozo.lopez@gmail.com', 'oid' => 'ghl_68e94130ed2ae160482805'],
    ['email' => 'horopeza8@gmail.com', 'oid' => 'ghl_68e94132e36dd125188143']
];

$deleted = 0;
$failed = 0;

foreach ($testUsers as $user) {
    try {
        echo "Procesando {$user['email']}...\n";
        
        // Buscar y eliminar suscripciones
        if ($subscriptions && isset($subscriptions['subscriptions'])) {
            foreach ($subscriptions['subscriptions'] as $sub) {
                if (isset($sub['customer']['oid']) && $sub['customer']['oid'] === $user['oid']) {
                    echo "  1️⃣  Eliminando suscripción (OID: {$sub['oid']})...\n";
                    $subResult = $service->deleteSubscription($sourceId, $sub['oid']);
                    
                    if ($subResult) {
                        echo "     ✅ Suscripción eliminada\n";
                    } else {
                        echo "     ⚠️  No se pudo eliminar la suscripción\n";
                    }
                }
            }
        }
        
        // Eliminar el cliente
        echo "  2️⃣  Eliminando cliente (OID: {$user['oid']})...\n";
        $custResult = $service->deleteCustomer($sourceId, $user['oid']);
        
        if ($custResult) {
            echo "     ✅ Cliente eliminado exitosamente\n";
            $deleted++;
            
            // Actualizar registro local
            $missingUser = \App\Models\MissingUser::where('baremetrics_customer_id', $user['oid'])->first();
            if ($missingUser) {
                $missingUser->update([
                    'import_status' => 'pending',
                    'baremetrics_customer_id' => null,
                    'imported_at' => null,
                    'import_notes' => 'Eliminado para re-importación con custom fields corregidos'
                ]);
                echo "     📝 Registro local actualizado a 'pending'\n";
            }
        } else {
            echo "     ❌ Error al eliminar cliente\n";
            $failed++;
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
        $failed++;
    }
}

echo "===================================\n";
echo "Eliminados: {$deleted}\n";
echo "Fallidos: {$failed}\n";
echo "===================================\n";
