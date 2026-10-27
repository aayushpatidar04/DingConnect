<?php

use App\Http\Controllers\Admin\AllowedNumberController;
use App\Http\Controllers\Admin\CommissionLevelController;
use App\Http\Controllers\Admin\CommissionRateController;
use App\Http\Controllers\Admin\CommissionReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\OperatorController as AdminOperatorController;
use App\Http\Controllers\Admin\OperatorPlanController as AdminOperatorPlanController;
use App\Http\Controllers\Admin\RetailerAnalyticsController;
use App\Http\Controllers\Admin\RetailerController as AdminRetailerController;
use App\Http\Controllers\Admin\RetailerLevelController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\OperatorController as ApiOperatorController;
use App\Http\Controllers\Api\TransactionController as ApiTransactionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NetworkOfferController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Retailer\CommissionController as RetailerCommissionController;
use App\Http\Controllers\Retailer\DashboardController as RetailerDashboardController;
use App\Http\Controllers\Retailer\OfferController as RetailerOfferController;
use App\Http\Controllers\Retailer\ProfileController as RetailerProfileController;
use App\Http\Controllers\Retailer\RechargeController;
use App\Http\Controllers\Retailer\TransactionController as RetailerTransactionController;
use App\Http\Controllers\Retailer\WalletController as RetailerWalletController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ==========================================================================
// PUBLIC
// ==========================================================================
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/network-offers', [NetworkOfferController::class, 'index'])->name('network-offers.index');

Route::get('/contact', fn () => Inertia::render('Landing/Contact/Index'))->name('contact');
Route::post('/contact', [LandingController::class, 'store'])->name('contact.submit');

// ==========================================================================
// AUTH
// ==========================================================================

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', fn () => Inertia::render('Auth/Register'))->name('register');
    Route::post('/register', [AuthController::class, 'store']);
    Route::get('/forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

    Route::post('/login/verify-otp', [AuthController::class, 'verifyLoginOtp'])->name('login.otp.verify');
    Route::post('/login/resend-otp', [AuthController::class, 'resendLoginOtp'])->name('login.otp.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ==========================================================================
// ADMIN
// ==========================================================================

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [RetailerAnalyticsController::class, 'index'])->name('analytics.index');

    Route::get('/retailers/export', [AdminRetailerController::class, 'export'])->name('retailers.export');
    Route::get('/retailers/create', [AdminRetailerController::class, 'create'])->name('retailers.create');
    Route::post('/retailers/store', [AdminRetailerController::class, 'store'])->name('retailers.store');
    Route::get('/retailers/{retailer}', [AdminRetailerController::class, 'show'])->name('retailers.show');
    Route::post('/retailers/{retailer}/approve', [AdminRetailerController::class, 'approve'])->name('retailers.approve');
    Route::post('/retailers/{retailer}/block', [AdminRetailerController::class, 'block'])->name('retailers.block');
    Route::post('/retailers/{retailer}/credit', [AdminRetailerController::class, 'creditWallet'])->name('retailers.credit');
    Route::post('/retailers/{retailer}/kyc', [AdminRetailerController::class, 'processKyc'])->name('retailers.kyc');
    Route::post('/retailers/{retailer}/toggle-status', [AdminRetailerController::class, 'toggleStatus'])->name('retailers.toggle-status');
    Route::resource('retailers', AdminRetailerController::class)->except(['show', 'approve', 'block', 'credit', 'kyc']);

    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export', [AdminTransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/refund', [AdminTransactionController::class, 'refund'])->name('transactions.refund');

    Route::resource('operators', AdminOperatorController::class);
    Route::post('operators/sync', [AdminOperatorController::class, 'syncFromDing'])->name('operators.sync');

    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

    // AllowedNumbers management
    Route::get('allowed-numbers/sample', [AllowedNumberController::class, 'downloadSample']);
    Route::post('allowed-numbers/import-file', [AllowedNumberController::class, 'importFile']);
    Route::get('allowed-numbers/files/{allowedNumberFile}/download', [AllowedNumberController::class, 'downloadFile']);
    Route::delete('allowed-numbers/files/{allowedNumberFile}', [AllowedNumberController::class, 'destroyFile']);
    // ...your existing index / store / update / destroy routes
    Route::resource('allowed-numbers', AllowedNumberController::class)->only(['index', 'store', 'update', 'destroy']);

    // Commission levels, rates and retailer levels
    Route::get('commission-levels', [CommissionLevelController::class, 'index'])->name('commission-levels.index');
    Route::post('commission-levels', [CommissionLevelController::class, 'store'])->name('commission-levels.store');
    Route::put('commission-levels/{level}', [CommissionLevelController::class, 'update'])->name('commission-levels.update');
    Route::delete('commission-levels/{level}', [CommissionLevelController::class, 'destroy'])->name('commission-levels.destroy');
    Route::post('commission-levels/{level}/copy-rates', [CommissionLevelController::class, 'copyRates'])->name('commission-levels.copy-rates');

    Route::post('commission-rates', [CommissionRateController::class, 'store'])->name('commission-rates.store');
    Route::put('commission-rates/{rate}', [CommissionRateController::class, 'update'])->name('commission-rates.update');
    Route::delete('commission-rates/{rate}', [CommissionRateController::class, 'destroy'])->name('commission-rates.destroy');

    Route::get('retailer-levels', [RetailerLevelController::class, 'index'])->name('retailer-levels.index');
    Route::post('retailer-levels/bulk', [RetailerLevelController::class, 'bulk'])->name('retailer-levels.bulk');
    Route::get('retailer-levels/{retailer}/history', [RetailerLevelController::class, 'history'])->name('retailer-levels.history');
    Route::put('retailer-levels/{retailer}', [RetailerLevelController::class, 'update'])->name('retailer-levels.update');

    Route::get('commissions', [CommissionReportController::class, 'index'])->name('commissions.index');
    Route::get('commissions/export', [CommissionReportController::class, 'export'])->name('commissions.export');

    // Offers
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::post('offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('offers/{offer}/redemptions', [OfferController::class, 'redemptions'])->name('offers.redemptions');
    Route::put('offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::delete('offers/{offer}', [OfferController::class, 'destroy'])->name('offers.destroy');

    Route::resource('network-plans', AdminOperatorPlanController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['network-plans' => 'operator_plan']);
});

// ==========================================================================
// RETAILER
// ==========================================================================

Route::prefix('retailer')->name('retailer.')->middleware(['auth', 'retailer', 'kyc.approved'])->group(function () {
    Route::get('/dashboard', [RetailerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/wallet', [RetailerWalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/topup', [RetailerWalletController::class, 'topUpPage'])->name('wallet.topup');
    Route::post('/wallet/topup', [RetailerWalletController::class, 'initiateTopUp'])->name('wallet.topup.initiate');
    Route::get('/wallet/ledger', [RetailerWalletController::class, 'ledger'])->name('wallet.ledger');

    Route::get('/recharge', [RechargeController::class, 'index'])->name('recharge.index');
    Route::post('/recharge', [RechargeController::class, 'initiate'])->name('recharge.initiate');
    Route::get('/recharge/operators', [RechargeController::class, 'getOperators'])->name('recharge.operators');
    Route::get('/recharge/providers', [RechargeController::class, 'getProvidersSimple'])->name('recharge.providers');
    Route::get('/recharge/provider-status', [RechargeController::class, 'getProviderStatus'])->name('recharge.provider-status');
    Route::get('/recharge/products', [RechargeController::class, 'getProducts'])->name('recharge.products');
    Route::get('/recharge/promotions', [RechargeController::class, 'getPromotions'])->name('recharge.promotions');
    Route::get('/recharge/pricing', [RechargeController::class, 'estimatePricing'])->name('recharge.pricing');
    Route::get('/recharge/validate-number', [RechargeController::class, 'validateNumber'])->name('recharge.validate-number');
    Route::get('/recharge/product-description', [RechargeController::class, 'productDescription'])->name('recharge.productDescription');
    Route::get('/recharge/plan-benefits', [RechargeController::class, 'planBenefits'])->name('recharge.plan-benefits');
    Route::get('/transactions', [RetailerTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [RetailerTransactionController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/{transaction}/poll', [RetailerTransactionController::class, 'poll'])->name('transactions.poll');
    Route::get('/transactions/{transaction}/receipt', [RetailerTransactionController::class, 'receipt'])->name('transactions.receipt');
    Route::get('/recharge/product-description', [RechargeController::class, 'productDescription'])->name('recharge.productDescription');

    Route::get('/recharge/valuetopup/operators', [RechargeController::class, 'getValuetopupOperators'])->name('recharge.valuetopup.operators');
    Route::post('/recharge/estimate-cost', [RechargeController::class, 'estimateCost'])->name('recharge.estimate-cost');

    Route::get('/commissions', [RetailerCommissionController::class, 'index'])->name('commissions.index');
    Route::get('/offers/eligible', [RetailerOfferController::class, 'eligible'])->name('offers.eligible');
    Route::get('/network-offers', [NetworkOfferController::class, 'index'])->name('network-offers');

    Route::get('/profile', [RetailerProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [RetailerProfileController::class, 'update'])->name('profile.update');
});

// ==========================================================================
// REST API (auth:sanctum)
// ==========================================================================

Route::prefix('api')->name('api.')->group(function () {
    Route::post('/auth/register', [App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
    Route::post('/auth/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login']);
    Route::post('/auth/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [App\Http\Controllers\Api\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('/countries', [CountryController::class, 'index']);
    Route::get('/countries/{iso}/operators', [ApiOperatorController::class, 'byCountry']);
    Route::get('/operators/search', [ApiOperatorController::class, 'search']);

    Route::post('/transactions/topup', [ApiTransactionController::class, 'topUp'])->middleware('auth:sanctum');
    Route::get('/transactions', [ApiTransactionController::class, 'history'])->middleware('auth:sanctum');
    Route::get('/transactions/{id}', [ApiTransactionController::class, 'show'])->middleware('auth:sanctum');
    Route::get('/transactions/{id}/status', [ApiTransactionController::class, 'status'])->middleware('auth:sanctum');
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
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
