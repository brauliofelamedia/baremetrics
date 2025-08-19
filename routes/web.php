<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\BaremetricsController;

Route::resource('cancellations', App\Http\Controllers\CancellationController::class)->except(['create', 'store', 'show', 'edit', 'update', 'destroy']);
Route::get('cancellations/load-more', [App\Http\Controllers\CancellationController::class, 'loadMoreCustomers'])->name('cancellations.load-more');
Route::post('cancellations/search', [App\Http\Controllers\CancellationController::class, 'searchByEmail'])->name('cancellations.search');

// Rutas para Stripe
Route::prefix('stripe')->name('stripe.')->group(function () {
    Route::get('/', [StripeController::class, 'index'])->name('index');
    Route::get('/customers', [StripeController::class, 'getCustomers'])->name('customers');
    Route::get('/customers/all', [StripeController::class, 'getAllCustomers'])->name('customers.all');
    Route::get('/customers/search', [StripeController::class, 'searchCustomers'])->name('customers.search');
    Route::get('/customers/{customer}', [StripeController::class, 'getCustomer'])->name('customers.show');
    Route::get('/config/publishable-key', [StripeController::class, 'getPublishableKey'])->name('config.key');
});

// Rutas para Baremetrics
Route::prefix('baremetrics')->name('baremetrics.')->group(function () {
    Route::get('/', [BaremetricsController::class, 'dashboard'])->name('dashboard');
    Route::get('/account', [BaremetricsController::class, 'getAccount'])->name('account');
    Route::get('/sources', [BaremetricsController::class, 'getSources'])->name('sources');
    Route::get('/users', [BaremetricsController::class, 'getUsers'])->name('users');
    Route::get('/config', [BaremetricsController::class, 'getConfig'])->name('config');
    
    // Rutas que requieren sourceId
    Route::prefix('{sourceId}')->group(function () {
        Route::get('/plans', [BaremetricsController::class, 'getPlans'])->name('plans');
        Route::get('/customers', [BaremetricsController::class, 'getCustomers'])->name('customers');
        Route::get('/subscriptions', [BaremetricsController::class, 'getSubscriptions'])->name('subscriptions');
    });
});