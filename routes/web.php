<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\BaremetricsController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GoHighLevelController;
use App\Http\Controllers\CancellationController;

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
    Route::get('cancellations/verify', [App\Http\Controllers\CancellationController::class, 'verifyCancellationToken'])->name('cancellation.verify');
    Route::get('cancellations/customer-ghl', [App\Http\Controllers\CancellationController::class, 'cancellationCustomerGHL'])->name('cancellation.customer.ghl');

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
    });
});

Route::prefix('gohighlevel')->middleware(['web'])->group(function () {
    Route::get('cancellation/form', [CancellationController::class, 'cancellation'])->name('cancellation.form');
    Route::get('cancellation/verify', [CancellationController::class, 'verifyCancellationToken'])->name('cancellation.verify');
    Route::get('cancellation/send-verification', [CancellationController::class, 'sendCancellationVerification'])->name('cancellation.send.verification');
    Route::get('cancellation/customer-ghl', [CancellationController::class, 'cancellationCustomerGHL'])->name('cancellation.customer.ghl');
    Route::get('cancellation', [CancellationController::class, 'cancellationCustomerGHL'])->where('email', '.*');
});

// Ruta home después del login
Route::get('/home', function () {
    return redirect()->route('admin.dashboard');
})->middleware('auth')->name('home');
