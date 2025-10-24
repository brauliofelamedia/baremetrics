<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$service = app(\App\Services\BaremetricsService::class);
$sourceId = 'd9d9a82f-5df7-4b1f-9cb0-6fdf7ab2c8a8';

echo "=== Eliminando 5 usuarios de prueba de Baremetrics ===\n\n";

$testUsers = [
    ['email' => 'yuvianat.holisticcoach@gmail.com', 'oid' => 'ghl_68e93a90c27bf370610288', 'sub_oid' => 'ghl_sub_68e93a912fde7176785609'],
    ['email' => 'lizzleony@gmail.com', 'oid' => 'ghl_68e93a91b745e131008559', 'sub_oid' => 'ghl_sub_68e93a9242a55029114610'],
    ['email' => 'marisolkfitbyme@gmail.com', 'oid' => 'ghl_68e93a92ca0bf793917671', 'sub_oid' => 'ghl_sub_68e93a933041c544977143'],
    ['email' => 'ninfa.cardozo.lopez@gmail.com', 'oid' => 'ghl_68e93a93bdb4a619479177', 'sub_oid' => 'ghl_sub_68e93a943c2ad172115357'],
    ['email' => 'horopeza8@gmail.com', 'oid' => 'ghl_68e93a94c0b40763331690', 'sub_oid' => 'ghl_sub_68e93a9532ce7692652248']
];

$deleted = 0;
$failed = 0;

foreach ($testUsers as $user) {
    try {
        echo "Procesando {$user['email']}...\n";
        
        // Primero eliminar la suscripción
        echo "  1️⃣  Eliminando suscripción (OID: {$user['sub_oid']})...\n";
        $subResult = $service->deleteSubscription($sourceId, $user['sub_oid']);
        
        if ($subResult) {
            echo "     ✅ Suscripción eliminada\n";
        } else {
            echo "     ⚠️  No se pudo eliminar la suscripción (puede que ya no exista)\n";
        }
        
        // Luego eliminar el cliente
        echo "  2️⃣  Eliminando cliente (OID: {$user['oid']})...\n";
        $custResult = $service->deleteCustomer($sourceId, $user['oid']);
        
        if ($custResult) {
            echo "     ✅ Cliente eliminado exitosamente\n";
            $deleted++;
            
            // También actualizar el registro local
            $missingUser = \App\Models\MissingUser::where('baremetrics_customer_id', $user['oid'])->first();
            if ($missingUser) {
                $missingUser->update([
                    'import_status' => 'pending',
                    'baremetrics_customer_id' => null,
                    'imported_at' => null,
                    'import_notes' => 'Eliminado para re-importación con código corregido'
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
