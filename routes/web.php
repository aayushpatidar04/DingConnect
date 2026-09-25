<?php

use App\Http\Controllers\Admin\{
    DashboardController as AdminDashboardController,
    OperatorController as AdminOperatorController,
    RetailerController as AdminRetailerController,
    SettingController as AdminSettingController,
    TransactionController as AdminTransactionController
};
use App\Http\Controllers\Api\{
    CountryController,
    OperatorController as ApiOperatorController,
    TransactionController as ApiTransactionController,
    WalletController as ApiWalletController,
    WebhookController
};
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Retailer\{
    DashboardController as RetailerDashboardController,
    ProfileController as RetailerProfileController,
    RechargeController,
    TransactionController as RetailerTransactionController,
    WalletController as RetailerWalletController
};
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ==========================================================================
// PUBLIC
// ==========================================================================
Route::get('/', [LandingController::class, 'index'])->name('home');

Route::get('/contact', fn () => Inertia::render('Contact/Index'))->name('contact');

// ==========================================================================
// AUTH
// ==========================================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', fn ($token) => Inertia::render('Auth/ResetPassword', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ==========================================================================
// ADMIN
// ==========================================================================
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::post('/retailers/{retailer}/approve', [AdminRetailerController::class, 'approve'])->name('retailers.approve');
    Route::post('/retailers/{retailer}/block', [AdminRetailerController::class, 'block'])->name('retailers.block');
    Route::post('/retailers/{retailer}/credit', [AdminRetailerController::class, 'creditWallet'])->name('retailers.credit');
    Route::post('/retailers/{retailer}/kyc', [AdminRetailerController::class, 'processKyc'])->name('retailers.kyc');
    Route::resource('retailers', AdminRetailerController::class)->except(['show', 'approve', 'block', 'credit', 'kyc']);

    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/refund', [AdminTransactionController::class, 'refund'])->name('transactions.refund');

    Route::resource('operators', AdminOperatorController::class);
    Route::post('operators/sync', [AdminOperatorController::class, 'syncFromDing'])->name('operators.sync');

    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
});

// ==========================================================================
// RETAILER
// ==========================================================================
Route::prefix('retailer')->name('retailer.')->middleware(['auth', 'retailer'])->group(function () {
    Route::get('/dashboard', [RetailerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/wallet', [RetailerWalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/topup', [RetailerWalletController::class, 'topUpPage'])->name('wallet.topup');
    Route::post('/wallet/topup', [RetailerWalletController::class, 'initiateTopUp'])->name('wallet.topup.initiate');
    Route::get('/wallet/ledger', [RetailerWalletController::class, 'ledger'])->name('wallet.ledger');

    Route::get('/recharge', [RechargeController::class, 'index'])->name('recharge.index');
    Route::post('/recharge', [RechargeController::class, 'initiate'])->name('recharge.initiate');
    Route::get('/recharge/operators', [RechargeController::class, 'getOperators'])->name('recharge.operators');
    Route::get('/recharge/provider-status', [RechargeController::class, 'getProviderStatus'])->name('recharge.provider-status');
    Route::get('/recharge/products', [RechargeController::class, 'getProducts'])->name('recharge.products');
    Route::get('/recharge/promotions', [RechargeController::class, 'getPromotions'])->name('recharge.promotions');
    Route::get('/recharge/pricing', [RechargeController::class, 'estimatePricing'])->name('recharge.pricing');

    Route::get('/transactions', [RetailerTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [RetailerTransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/{transaction}/poll', [RetailerTransactionController::class, 'poll'])->name('transactions.poll');
    Route::get('/transactions/{transaction}/receipt', [RetailerTransactionController::class, 'receipt'])->name('transactions.receipt');
    Route::get('/recharge/product-description', [RechargeController::class, 'productDescription'])->name('recharge.productDescription');

    Route::get('/profile', [RetailerProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [RetailerProfileController::class, 'update'])->name('profile.update');
});

// ==========================================================================
// REST API (auth:sanctum)
// ==========================================================================
Route::prefix('api')->name('api.')->group(function () {
    Route::post('/auth/register', [\App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
    Route::post('/auth/login', [\App\Http\Controllers\Api\Auth\AuthController::class, 'login']);
    Route::post('/auth/logout', [\App\Http\Controllers\Api\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [\App\Http\Controllers\Api\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('/countries', [CountryController::class, 'index']);
    Route::get('/countries/{iso}/operators', [ApiOperatorController::class, 'byCountry']);
    Route::get('/operators/search', [ApiOperatorController::class, 'search']);

    Route::post('/transactions/topup', [ApiTransactionController::class, 'topUp'])->middleware('auth:sanctum');
    Route::get('/transactions', [ApiTransactionController::class, 'history'])->middleware('auth:sanctum');
    Route::get('/transactions/{id}', [ApiTransactionController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/transactions/{id}/status', [ApiTransactionController::class, 'status'])->middleware('auth:sanctum');
    Route::post('/webhooks/ding', [WebhookController::class, 'dingCallback']);
});

// ==========================================================================
// BROADCASTING
// ==========================================================================
Route::get('/broadcasting/auth', function () {
    return Broadcast::auth(request()->user());
})->middleware('auth');

// ==========================================================================
// CONVENIENCE REDIRECTS
// ==========================================================================
Route::get('/admin', fn () => redirect('/admin/dashboard'))->middleware(['auth', 'admin']);
Route::get('/retailer', fn () => redirect('/retailer/dashboard'))->middleware(['auth', 'retailer']);

// ==========================================================================
// LARAVEL BREEZE PROFILE
// ==========================================================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});
