<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoHighLevelController;
use App\Http\Controllers\Api\GHLFilterController;
use App\Http\Controllers\Api\BaremetricsCreateController;
use App\Http\Controllers\CancellationController;

Route::prefix('gohighlevel')->middleware(['api_key'])->group(function () {
    Route::post('contact/update', [GoHighLevelController::class, 'updateCustomerFromGHL']);
    //Route::post('membership/update', [GoHighLevelController::class, 'updateStatusMembershipGHL']);
    Route::post('membership/status', [GoHighLevelController::class, 'getSubscriptionbyEmail']); 
});

// Rutas para filtrado de usuarios por tags
Route::prefix('ghl')->middleware(['api_key'])->group(function () {
    Route::get('filter/users', [GHLFilterController::class, 'filterUsersByTags']);
    Route::get('tags/statistics', [GHLFilterController::class, 'getTagStatistics']);
});

// Rutas para creación de recursos en Baremetrics
Route::prefix('baremetrics')->middleware(['api_key'])->group(function () {
    Route::get('source-id', [BaremetricsCreateController::class, 'getSourceId']);
    Route::post('customer', [BaremetricsCreateController::class, 'createCustomer']);
    Route::post('plan', [BaremetricsCreateController::class, 'createPlan']);
    Route::post('subscription', [BaremetricsCreateController::class, 'createSubscription']);
    Route::post('complete-setup', [BaremetricsCreateController::class, 'createCompleteSetup']);
    Route::post('check-email', [App\Http\Controllers\BaremetricsController::class, 'checkEmailExists']);
});