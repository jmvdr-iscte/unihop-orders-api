<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\NashController;

Route::get('/health',[ServerController::class, 'health']);
   
Route::get('/orders', [OrderController::class, 'index']);

Route::middleware('api_key')->group(function () {
    Route::patch('/order/{job_id}', [OrderController::class, 'update']);
});

Route::middleware('webhook')->group(function () {
    Route::post('orders', [NashController::class, 'handleJob']);
});
