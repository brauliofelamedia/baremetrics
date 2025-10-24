<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Configurar para producción
config(['services.baremetrics.environment' => 'production']);

// Inicializar servicios
$baremetrics = new \App\Services\BaremetricsService();
$baremetrics->reinitializeConfiguration();

$email = 'braulio@felamedia.com';

echo "=== BUSCANDO USUARIO PARA PRUEBA DE CANCELACIÓN ===\n\n";
echo "Email a buscar: {$email}\n\n";

// Buscar el cliente por email
$customers = $baremetrics->getCustomersByEmail($email);

if (empty($customers)) {
    echo "❌ No se encontró el usuario con email: {$email}\n";
    echo "Verifica que el email sea correcto.\n";
    exit(1);
}

echo "✅ Usuario encontrado!\n\n";

$customer = is_array($customers) && isset($customers[0]) ? $customers[0] : $customers;

echo "=== DATOS DEL CLIENTE ===\n";
echo "Customer OID: " . ($customer['oid'] ?? 'N/A') . "\n";
echo "Nombre: " . ($customer['name'] ?? 'N/A') . "\n";
echo "Email: " . ($customer['email'] ?? 'N/A') . "\n";
echo "Cancelado: " . (($customer['is_canceled'] ?? false) ? 'Sí' : 'No') . "\n";

// Verificar suscripciones activas en Stripe
$customerId = $customer['oid'];

try {
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    
    $allSubscriptions = \Stripe\Subscription::all([
        'customer' => $customerId,
        'status' => 'all',
        'limit' => 100
    ]);
    
    $activeSubscriptions = [];
    $canceledSubscriptions = [];
    
    foreach ($allSubscriptions->data as $subscription) {
        if ($subscription->status === 'active' || $subscription->status === 'trialing') {
            $activeSubscriptions[] = $subscription;
        } else {
            $canceledSubscriptions[] = $subscription;
        }
    }
    
    echo "\n=== SUSCRIPCIONES ===\n";
    echo "Activas: " . count($activeSubscriptions) . "\n";
    echo "Canceladas: " . count($canceledSubscriptions) . "\n\n";
    
    if (!empty($activeSubscriptions)) {
        echo "📋 SUSCRIPCIONES ACTIVAS:\n";
        foreach ($activeSubscriptions as $subscription) {
            $plan = $subscription->plan;
            echo "  • ID: {$subscription->id}\n";
            echo "    Plan: {$plan->nickname} ({$plan->amount}/100 {$plan->currency})\n";
            echo "    Estado: {$subscription->status}\n";
            echo "    Período actual: " . date('Y-m-d', $subscription->current_period_start) . " - " . date('Y-m-d', $subscription->current_period_end) . "\n";
            echo "\n";
        }
        
        echo "\n=== URL PARA PRUEBA DE CANCELACIÓN ===\n";
        echo "Accede a esta URL para iniciar la cancelación:\n\n";
        echo "https://baremetrics.local/gohighlevel/cancellation/survey/{$customerId}\n\n";
        
        echo "O si prefieres usar el token de verificación:\n";
        echo "https://baremetrics.local/admin/cancellations/send-verification?email={$email}\n\n";
        
    } else {
        echo "⚠️ ADVERTENCIA: Este usuario NO tiene suscripciones activas.\n";
        echo "No podrás completar una cancelación real.\n\n";
        
        if (!empty($canceledSubscriptions)) {
            echo "📋 SUSCRIPCIONES PREVIAMENTE CANCELADAS:\n";
            foreach ($canceledSubscriptions as $subscription) {
                echo "  • ID: {$subscription->id}\n";
                echo "    Estado: {$subscription->status}\n";
                if ($subscription->canceled_at) {
                    echo "    Cancelada el: " . date('Y-m-d H:i:s', $subscription->canceled_at) . "\n";
                }
                echo "\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "\n❌ Error al consultar Stripe: " . $e->getMessage() . "\n";
    echo "\nIntentando con método alternativo...\n\n";
    
    // Método alternativo usando los planes de Baremetrics
    if (isset($customer['current_plans']) && !empty($customer['current_plans'])) {
        echo "📋 PLANES ACTUALES EN BAREMETRICS:\n";
        foreach ($customer['current_plans'] as $plan) {
            echo "  • Plan OID: " . ($plan['oid'] ?? 'N/A') . "\n";
            echo "    Nombre: " . ($plan['name'] ?? 'N/A') . "\n";
            echo "\n";
        }
        
        echo "\n=== URL PARA PRUEBA DE CANCELACIÓN ===\n";
        echo "https://baremetrics.local/gohighlevel/cancellation/survey/{$customerId}\n\n";
    } else {
        echo "⚠️ No se encontraron planes activos para este usuario.\n";
    }
}

echo "\n=== INSTRUCCIONES PARA LA PRUEBA ===\n";
echo "1. Copia la URL del survey de cancelación\n";
echo "2. Abre la URL en tu navegador\n";
echo "3. Completa el formulario con un motivo de cancelación\n";
echo "4. Agrega comentarios opcionales si lo deseas\n";
echo "5. Envía el formulario\n";
echo "6. Verifica los logs en storage/logs/laravel.log\n";
echo "7. Verifica en Baremetrics que los campos se actualizaron:\n";
echo "   - Active Subscription? → No\n";
echo "   - Canceled? → Yes\n";
echo "   - Cancellation Reason → El motivo que seleccionaste\n\n";

echo "=== VERIFICAR LOGS ===\n";
echo "Después de enviar el formulario, ejecuta:\n";
echo "tail -f storage/logs/laravel.log | grep -i 'cancelación\\|barecancel\\|survey'\n\n";
