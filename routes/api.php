<?php

use App\Http\Controllers\Api\AuthApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthApiController::class, 'logout']);
});

Route::middleware(['auth:sanctum'])->get('/user', [AuthApiController::class, 'user']);

Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);
    Route::get('/activity', [\App\Http\Controllers\Api\DashboardController::class, 'activity']);
    Route::get('/chart', [\App\Http\Controllers\Api\DashboardController::class, 'chart']);
});

