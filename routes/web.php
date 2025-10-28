<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\BaremetricsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GoHighLevelController;
use App\Http\Controllers\CancellationController;

// Rutas públicas de cancelación (DEBEN estar ANTES de cualquier middleware de autenticación)
Route::prefix('gohighlevel')->group(function () {
    Route::get('cancellation', [CancellationController::class, 'index'])->name('cancellation.index');
    Route::get('cancellation/form', [CancellationController::class, 'cancellation'])->name('cancellation.form');
    Route::get('cancellation/survey/{customer_id}', [CancellationController::class, 'surveyCancellation'])->name('cancellation.survey');
    Route::post('cancellation/survey/save', [CancellationController::class, 'surveyCancellationSave'])->name('cancellation.survey.save');
    Route::get('cancellation/verify', [CancellationController::class, 'verifyCancellationToken'])->name('cancellation.verify');
    Route::get('cancellation/send-verification', [CancellationController::class, 'sendCancellationVerification'])->name('cancellation.send.verification');
    Route::get('cancellation/customer-ghl', [CancellationController::class, 'cancellationCustomerGHL'])->name('cancellation.customer.ghl');
    Route::get('cancellation/manual/{customer_id?}/{subscription_id?}', [CancellationController::class, 'manualCancellation'])->name('cancellation.manual');
    Route::post('cancellation/cancel', [CancellationController::class, 'publicCancelSubscription'])->name('cancellation.cancel');
    Route::get('cancellation/search', [CancellationController::class, 'cancellationCustomerGHL'])->where('email', '.*');
});

// Ruta adicional para acceso directo a verificación (sin prefijo gohighlevel)
Route::get('cancellation/verify', [CancellationController::class, 'verifyCancellationToken'])->name('cancellation.verify.direct');

// Ruta principal - redirigir al dashboard si está autenticado
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
});

// Rutas de autenticación
Auth::routes(['register' => false]);

// Rutas del admin panel (protegidas por middleware)
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard principal
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    
    // Gestión de usuarios y roles
    Route::resource('users', App\Http\Controllers\UserController::class);
    Route::post('users/{user}/toggle-status', [App\Http\Controllers\UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::resource('roles', App\Http\Controllers\RoleController::class);
    Route::resource('permissions', App\Http\Controllers\PermissionController::class);
    
    // Sistema - Configuraciones (Nuevo sistema unificado)
    Route::prefix('system-config')->name('system-config.')->group(function () {
        Route::get('/', [App\Http\Controllers\SystemConfigurationController::class, 'index'])->name('index');
        Route::get('/edit', [App\Http\Controllers\SystemConfigurationController::class, 'edit'])->name('edit');
        Route::put('/update', [App\Http\Controllers\SystemConfigurationController::class, 'update'])->name('update');
        Route::get('/remove-logo', [App\Http\Controllers\SystemConfigurationController::class, 'removeLogo'])->name('remove-logo');
        Route::get('/remove-favicon', [App\Http\Controllers\SystemConfigurationController::class, 'removeFavicon'])->name('remove-favicon');
    });
    
    // Sistema - Configuraciones principales
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/', [App\Http\Controllers\SystemController::class, 'index'])->name('index');
        Route::get('/edit', [App\Http\Controllers\SystemController::class, 'edit'])->name('edit');
        Route::put('/update', [App\Http\Controllers\SystemController::class, 'update'])->name('update');
        Route::get('/info', [App\Http\Controllers\SystemController::class, 'info'])->name('info');
        Route::get('/clear-cache', [App\Http\Controllers\SystemController::class, 'clearCache'])->name('clear-cache');
        Route::get('/download-logs', [App\Http\Controllers\SystemController::class, 'downloadLogs'])->name('download-logs');
        Route::get('/remove-logo', [App\Http\Controllers\SystemController::class, 'removeLogo'])->name('remove-logo');
        Route::get('/remove-favicon', [App\Http\Controllers\SystemController::class, 'removeFavicon'])->name('remove-favicon');
    });
    
    // Cancelaciones (ahora bajo admin)
    Route::resource('cancellations', App\Http\Controllers\CancellationController::class)->except(['create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('cancellations/load-more', [App\Http\Controllers\CancellationController::class, 'loadMoreCustomers'])->name('cancellations.load-more');
    Route::get('cancellations/search/{search?}', [App\Http\Controllers\CancellationController::class, 'searchByEmail'])->name('cancellations.search');
    Route::get('cancellations/{customer_id}/{subscription_id}', [App\Http\Controllers\CancellationController::class, 'manualCancellation'])->name('cancellations.manual');
    Route::post('cancellations/stripe/cancel', [App\Http\Controllers\CancellationController::class, 'cancelSubscription'])->name('cancellations.cancel');
    Route::get('cancellations/send-verification', [App\Http\Controllers\CancellationController::class, 'sendCancellationVerification'])->name('cancellations.send-verification');
    Route::get('cancellations/customer-ghl', [App\Http\Controllers\CancellationController::class, 'cancellationCustomerGHL'])->name('cancellation.customer.ghl');
    
    // Gestión administrativa de tokens de cancelación
    Route::get('cancellation-tokens', [App\Http\Controllers\CancellationController::class, 'adminTokens'])->name('cancellation-tokens');
    Route::post('cancellation-tokens/invalidate', [App\Http\Controllers\CancellationController::class, 'invalidateToken'])->name('cancellation-tokens.invalidate');

    //GoHighLevel
    Route::prefix('ghlevel')->name('ghlevel.')->group(function () {
        Route::get('/initial', [GoHighLevelController::class, 'initial'])->name('initial');
        Route::get('/authorization', [GoHighLevelController::class, 'authorization'])->name('authorize');
        Route::get('/custom-fields', [GoHighLevelController::class, 'getCustomFields'])->name('custom_fields');
        Route::get('/contacts/{email?}', [GoHighLevelController::class, 'getContacts'])->name('contacts');
    });

    // Rutas para Stripe (ahora bajo admin)
    // PayPal Routes
    Route::prefix('paypal')->name('paypal.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PayPalController::class, 'index'])->name('index');
        Route::get('/subscriptions', [\App\Http\Controllers\PayPalController::class, 'getSubscriptions'])->name('subscriptions');
        Route::get('/subscriptions/{subscriptionId}', [\App\Http\Controllers\PayPalController::class, 'getSubscriptionDetails'])->name('subscription.details');
        Route::get('/stats', [\App\Http\Controllers\PayPalController::class, 'getSubscriptionStats'])->name('stats');
    });

    // Stripe Routes
    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::get('/', [StripeController::class, 'index'])->name('index');
        Route::post('/customers/cancel', [StripeController::class, 'cancelSubscription'])->name('customers.cancel');
        Route::get('/customers', [StripeController::class, 'index'])->name('customers'); // Vista principal
        Route::get('/customers/data', [StripeController::class, 'getCustomers'])->name('customers.data'); // API data
        Route::get('/customers/all', [StripeController::class, 'getAllCustomers'])->name('customers.all');
        Route::get('/customers/load-more', [StripeController::class, 'loadMoreCustomers'])->name('customers.load-more'); // Nueva ruta
        Route::get('/customers/search', [StripeController::class, 'searchCustomers'])->name('customers.search');
        Route::get('/customers/{customer}', [StripeController::class, 'getCustomer'])->name('customers.show');
        Route::get('/config/publishable-key', [StripeController::class, 'getPublishableKey'])->name('config.key');
        
        // Ruta de prueba temporal para verificar conectividad
        Route::get('/test-connection', function() {
            $stripeService = new App\Services\StripeService();
            $result = $stripeService->testConnection();
            return response()->json($result);
        })->name('test-connection');
    });

    // Rutas para Baremetrics (ahora bajo admin)
    Route::prefix('baremetrics')->name('baremetrics.')->group(function () {
        Route::get('/', [BaremetricsController::class, 'dashboard'])->name('dashboard');
        Route::get('/account', [BaremetricsController::class, 'getAccount'])->name('account');
        Route::get('/sources', [BaremetricsController::class, 'getSources'])->name('sources');
        Route::get('/users', [BaremetricsController::class, 'getUsers'])->name('users');
        Route::get('/config', [BaremetricsController::class, 'getConfig'])->name('config');
        Route::get('/create/customer', [BaremetricsController::class, 'createCustomer'])->name('create.customer');
        Route::post('/check-email', [BaremetricsController::class, 'checkEmailExists'])->name('check.email');
        // Gestión de usuarios fallidos
        Route::get('/failed-users', [BaremetricsController::class, 'showFailedUsers'])->name('failed-users');
        Route::post('/delete-failed-users', [BaremetricsController::class, 'deleteFailedUsers'])->name('delete-failed-users');
        // Eliminar usuarios por plan específico
        Route::get('/delete-users-by-plan', [BaremetricsController::class, 'showDeleteUsersByPlan'])->name('delete-users-by-plan.show');
        Route::post('/delete-users-by-plan', [BaremetricsController::class, 'deleteUsersByPlan'])->name('delete-users-by-plan');
        // Página para ejecutar la actualización de campos desde GHL
        Route::get('/update-fields', [BaremetricsController::class, 'showUpdateFields'])->name('update-fields');
        // Inicia el proceso en background (dispara un comando Artisan)
        Route::post('/update-fields/start', [BaremetricsController::class, 'updateCustomerFieldsFromGHL'])->name('update-fields.start');
        // Estado / progreso
        Route::get('/update-fields/status', [BaremetricsController::class, 'getUpdateStatus'])->name('update-fields.status');

        // Rutas que requieren sourceId
        Route::prefix('{sourceId}')->group(function () {
            Route::get('/plans', [BaremetricsController::class, 'getPlans'])->name('plans');
            Route::get('/customers', [BaremetricsController::class, 'getCustomers'])->name('customers');
            Route::get('/subscriptions', [BaremetricsController::class, 'getSubscriptions'])->name('subscriptions');
        });

        // Ruta para crear planes de prueba en sandbox
        Route::get('sandbox/createPlan', [BaremetricsController::class, 'createPlanSandbox'])->name('create.plan.sandbox');
        Route::get('sandbox/createCustomer', [BaremetricsController::class, 'createCustomerSandbox'])->name('create.customer.sandbox');
    });

    // Rutas para Comparaciones GHL vs Baremetrics
    Route::prefix('ghl-comparison')->name('ghl-comparison.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\GHLComparisonController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\GHLComparisonController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\GHLComparisonController::class, 'store'])->name('store');
        Route::get('/{comparison}', [App\Http\Controllers\Admin\GHLComparisonController::class, 'show'])->name('show');
        Route::get('/{comparison}/processing', [App\Http\Controllers\Admin\GHLComparisonController::class, 'processing'])->name('processing');
        Route::post('/{comparison}/start-processing', [App\Http\Controllers\Admin\GHLComparisonController::class, 'startProcessing'])->name('start-processing');
        Route::get('/{comparison}/progress', [App\Http\Controllers\Admin\GHLComparisonController::class, 'getProgress'])->name('progress');
        Route::get('/{comparison}/missing-users', [App\Http\Controllers\Admin\GHLComparisonController::class, 'missingUsers'])->name('missing-users');
        Route::post('/{comparison}/import-users', [App\Http\Controllers\Admin\GHLComparisonController::class, 'importUsers'])->name('import-users');
        Route::post('/{comparison}/import-all-users', [App\Http\Controllers\Admin\GHLComparisonController::class, 'importAllUsers'])->name('import-all-users');
        Route::post('/{comparison}/import-all-users-with-plan', [App\Http\Controllers\Admin\GHLComparisonController::class, 'importAllUsersWithPlan'])->name('import-all-users-with-plan');
        Route::post('/missing-users/{user}/retry-import', [App\Http\Controllers\Admin\GHLComparisonController::class, 'retryImport'])->name('retry-import');
        Route::post('/missing-users/{user}/import-with-plan', [App\Http\Controllers\Admin\GHLComparisonController::class, 'importUserWithPlan'])->name('import-with-plan');
        Route::post('/{comparison}/delete-imported-users', [App\Http\Controllers\Admin\GHLComparisonController::class, 'deleteImportedUsers'])->name('delete-imported-users');
        Route::get('/{comparison}/download-missing-users', [App\Http\Controllers\Admin\GHLComparisonController::class, 'downloadMissingUsers'])->name('download-missing-users');
        Route::delete('/{comparison}', [App\Http\Controllers\Admin\GHLComparisonController::class, 'destroy'])->name('destroy');
    });
});

// Ruta home después del login
Route::get('/home', function () {
    return redirect()->route('admin.dashboard');
})->middleware('auth')->name('home');
