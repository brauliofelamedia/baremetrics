<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$customerId = 'cus_T1weUTpi3zzsSV';

echo "=== VERIFICANDO SUSCRIPCIONES EN STRIPE ===\n\n";
echo "Customer ID: {$customerId}\n\n";

// Configurar Stripe API Key
$stripeKey = env('STRIPE_SECRET_KEY');
if (!$stripeKey) {
    echo "❌ Error: No se encontró STRIPE_SECRET_KEY en .env\n";
    exit(1);
}

echo "Usando Stripe API Key: " . substr($stripeKey, 0, 20) . "...\n\n";

\Stripe\Stripe::setApiKey($stripeKey);

try {
    // Obtener todas las suscripciones del cliente
    $subscriptions = \Stripe\Subscription::all([
        'customer' => $customerId,
        'limit' => 100
    ]);
    
    echo "Total de suscripciones encontradas: " . count($subscriptions->data) . "\n\n";
    
    if (empty($subscriptions->data)) {
        echo "❌ Este cliente NO tiene ninguna suscripción en Stripe.\n";
        echo "No podrás realizar una cancelación porque no hay nada que cancelar.\n\n";
        
        echo "=== OPCIONES ===\n";
        echo "1. Usa otro customer_id que tenga suscripciones activas\n";
        echo "2. Crea una suscripción de prueba para este cliente primero\n";
        exit(1);
    }
    
    $activeCount = 0;
    $canceledCount = 0;
    
    foreach ($subscriptions->data as $subscription) {
        $status = $subscription->status;
        $isActive = in_array($status, ['active', 'trialing']);
        
        if ($isActive) {
            $activeCount++;
            echo "✅ SUSCRIPCIÓN ACTIVA:\n";
        } else {
            $canceledCount++;
            echo "❌ SUSCRIPCIÓN NO ACTIVA:\n";
        }
        
        echo "   ID: {$subscription->id}\n";
        echo "   Estado: {$status}\n";
        echo "   Plan: {$subscription->plan->id}\n";
        
        if ($subscription->plan->nickname) {
            echo "   Nombre: {$subscription->plan->nickname}\n";
        }
        
        echo "   Precio: " . ($subscription->plan->amount / 100) . " {$subscription->plan->currency}\n";
        echo "   Intervalo: {$subscription->plan->interval}\n";
        
        if ($subscription->canceled_at) {
            echo "   Cancelada el: " . date('Y-m-d H:i:s', $subscription->canceled_at) . "\n";
        }
        
        if ($subscription->cancel_at_period_end) {
            echo "   ⚠️ Cancelación programada al final del período\n";
            echo "   Termina el: " . date('Y-m-d H:i:s', $subscription->current_period_end) . "\n";
        }
        
        echo "\n";
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "Activas: {$activeCount}\n";
    echo "No activas: {$canceledCount}\n\n";
    
    if ($activeCount > 0) {
        echo "✅ Este cliente tiene {$activeCount} suscripción(es) activa(s).\n";
        echo "Puedes proceder con la prueba de cancelación:\n\n";
        echo "https://baremetrics.local/gohighlevel/cancellation/survey/{$customerId}\n\n";
    } else {
        echo "❌ Este cliente NO tiene suscripciones activas.\n";
        echo "Todas las suscripciones ya están canceladas o en otro estado.\n";
        echo "No podrás completar una cancelación real.\n\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error al consultar Stripe: " . $e->getMessage() . "\n";
    echo "\nDetalles:\n";
    echo $e->getTraceAsString() . "\n";
}
