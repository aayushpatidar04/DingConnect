<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/countries/{iso}/operators', [OperatorController::class, 'byCountry']);
Route::get('/operators/search', [OperatorController::class, 'search']);

Route::post('/webhooks/ding',    [WebhookController::class, 'dingCallback']);
Route::post('/webhooks/stripe',  [App\Http\Controllers\Api\PaymentWebhookController::class, 'webhook'])->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    Route::prefix('transactions')->group(function () {
        Route::post('/topup',     [TransactionController::class, 'topUp']);
        Route::get('/',           [TransactionController::class, 'history']);
        Route::get('/{id}',       [TransactionController::class, 'show'])->whereNumber('id');
        Route::get('/{id}/status', [TransactionController::class, 'status'])->whereNumber('id');
    });
});
