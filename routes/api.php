<?php

use App\Http\Controllers\Admin\AllowedNumberController as AdminAllowedNumberController;
use App\Http\Controllers\Admin\RetailerController as WebRetailerController;
use App\Http\Controllers\Admin\TransactionController as WebTransactionController;
use App\Http\Controllers\Api\Admin\OperatorController as AdminOperatorController;
use App\Http\Controllers\Api\Admin\RetailerController as AdminRetailerController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Retailer\WalletController as RetailerWalletController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Retailer\RechargeController;
use App\Http\Controllers\Retailer\TransactionController as RetailerTransactionController;
use App\Http\Controllers\Retailer\WalletController as RetailerWebWalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::get('/countries', [CountryController::class, 'index']);
Route::get('/countries/{iso}/operators', [OperatorController::class, 'byCountry']);
Route::get('/operators/search', [OperatorController::class, 'search']);

Route::post('/webhooks/ding', [WebhookController::class, 'dingCallback']);
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'webhook']);

Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);

    Route::middleware('admin')->prefix('admin')->name('api.admin.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'admin']);

        Route::get('/retailers/export', [WebRetailerController::class, 'export']);
        Route::apiResource('retailers', AdminRetailerController::class);
        Route::post('/retailers/{retailer}/approve', [AdminRetailerController::class, 'approve']);
        Route::post('/retailers/{retailer}/block', [AdminRetailerController::class, 'block']);
        Route::post('/retailers/{retailer}/credit', [AdminRetailerController::class, 'creditWallet']);
        Route::post('/retailers/{retailer}/kyc', [AdminRetailerController::class, 'processKyc']);

        Route::get('/transactions', [AdminTransactionController::class, 'index']);
        Route::get('/transactions/export', [WebTransactionController::class, 'export']);
        Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show']);
        Route::post('/transactions/{transaction}/refund', [AdminTransactionController::class, 'refund']);

        Route::post('/operators/sync', [AdminOperatorController::class, 'syncFromDing']);
        Route::apiResource('operators', AdminOperatorController::class);

        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings', [AdminSettingController::class, 'update']);

        Route::get('/allowed-numbers/sample', [AdminAllowedNumberController::class, 'downloadSample']);
        Route::post('/allowed-numbers/import-file', [AdminAllowedNumberController::class, 'importFile']);
        Route::get('/allowed-numbers/files/{allowedNumberFile}/download', [AdminAllowedNumberController::class, 'downloadFile']);
        Route::delete('/allowed-numbers/files/{allowedNumberFile}', [AdminAllowedNumberController::class, 'destroyFile']);
        Route::apiResource('allowed-numbers', AdminAllowedNumberController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware(['retailer', 'kyc.approved'])->prefix('retailer')->name('api.retailer.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'retailer']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);

        Route::get('/wallet', [RetailerWalletController::class, 'index']);
        Route::get('/wallet/topup', [RetailerWalletController::class, 'topUpDetails']);
        Route::get('/wallet/ledger', [RetailerWalletController::class, 'ledger']);
        Route::post('/wallet/topup', [RetailerWebWalletController::class, 'initiateTopUp']);

        Route::get('/recharge/operators', [RechargeController::class, 'getOperators']);
        Route::get('/recharge/providers', [RechargeController::class, 'getProvidersSimple']);
        Route::get('/recharge/valuetopup/operators', [RechargeController::class, 'getValuetopupOperators']);
        Route::get('/recharge/provider-status', [RechargeController::class, 'getProviderStatus']);
        Route::get('/recharge/products', [RechargeController::class, 'getProducts']);
        Route::get('/recharge/promotions', [RechargeController::class, 'getPromotions']);
        Route::get('/recharge/pricing', [RechargeController::class, 'estimatePricing']);
        Route::post('/recharge/estimate-cost', [RechargeController::class, 'estimateCost']);
        Route::get('/recharge/validate-number', [RechargeController::class, 'validateNumber']);
        Route::get('/recharge/product-description', [RechargeController::class, 'productDescription']);
        Route::post('/recharge', [RechargeController::class, 'initiate']);

        Route::get('/transactions', [TransactionController::class, 'history']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show'])->whereNumber('id');
        Route::get('/transactions/{id}/status', [TransactionController::class, 'status'])->whereNumber('id');
        Route::get('/transactions/{transaction}/receipt', [RetailerTransactionController::class, 'receipt']);
    });
});

Route::middleware('auth:sanctum')->prefix('transactions')->group(function (): void {
    Route::post('/topup', [RechargeController::class, 'initiate'])->middleware(['retailer', 'kyc.approved']);
    Route::get('/', [TransactionController::class, 'history'])->middleware(['retailer', 'kyc.approved']);
    Route::get('/{id}', [TransactionController::class, 'show'])->whereNumber('id')->middleware(['retailer', 'kyc.approved']);
    Route::get('/{id}/status', [TransactionController::class, 'status'])->whereNumber('id')->middleware(['retailer', 'kyc.approved']);
});
