<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\OrderController;

Route::get('/health',[ServerController::class, 'health']);

Route::get('/orders', [OrderController::class, 'index']);
Route::patch('/orders/{job_id}', [OrderController::class, 'update']);
