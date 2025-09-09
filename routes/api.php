<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoHighLevelController;
use App\Http\Controllers\CancellationController;

Route::prefix('gohighlevel')->middleware(['api_key'])->group(function () {
    Route::post('contact/update', [GoHighLevelController::class, 'updateCustomerFromGHL']);
});

Route::prefix('gohighlevel')->group(function () {
    Route::get('cancellation', [CancellationController::class, 'cancellationCustomerGHL'])->where('email', '.*');
});