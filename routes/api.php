<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TV\TVCodeController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ValidateToken;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/generate-tv-code', action: [TVCodeController::class, 'generate']);
Route::post('/poll-tv-code', [TVCodeController::class, 'poll']);

// Protected Routes
Route::middleware([ValidateToken::class])->group(function () {
    Route::post('/active-tv-code', [TVCodeController::class, 'activate']);
});