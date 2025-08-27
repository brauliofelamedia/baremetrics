<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoHighLevelController;

Route::prefix('gohighlevel')->middleware(['api_key'])->group(function () {
    Route::post('contact/update', [GoHighLevelController::class, 'updateCustomerFromGHL']);
});