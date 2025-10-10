<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\Api\AuthController;

Route::middleware(['secure.headers', 'throttle:5,1'])->group(function () {
    Route::post('/api/auth/login', [AuthController::class, 'login']);
    Route::post('/api/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});
