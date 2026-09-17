<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Models\WalletTopup;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $walletService = app(WalletService::class);
        $wallet = $walletService->getWallet($user);
        $availableBalance = $walletService->getAvailableBalance($wallet);

        $topups = WalletTopup::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return Inertia::render('Retailer/Wallet/Index', compact('wallet', 'availableBalance', 'topups'));
    }

    public function topUpPage(Request $request)
    {
        $user = $request->user();
        $walletService = app(WalletService::class);
        $wallet = $walletService->getWallet($user);
        $availableBalance = $walletService->getAvailableBalance($wallet);
        $stripePublishableKey = config('platform.payment.stripe.publishable_key');

        return Inertia::render('Retailer/Wallet/TopUp', compact('wallet', 'availableBalance', 'stripePublishableKey'));
    }

    public function initiateTopUp(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:' . config('platform.pricing.min_recharge', 10) . '|max:' . config('platform.pricing.max_recharge', 10000),
        ]);

        $paymentService = app(PaymentService::class);

        $user = $request->user();
        $result = $paymentService->createPaymentIntent(
            (float) $request->amount,
            config('platform.wallet.currency', 'GBP'),
            $user->id,
            $user->email
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 400);
        }

        $feePercentage = config('platform.wallet.load_fee_percentage', 0);
        $feeFlat = config('platform.wallet.load_fee_flat', 0);
        $feeAmount = ($request->amount * $feePercentage / 100) + $feeFlat;
        $totalCharged = $request->amount + $feeAmount;

        $topup = WalletTopup::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'currency' => config('platform.wallet.currency', 'GBP'),
            'fee_amount' => $feeAmount,
            'fee_percentage' => $feePercentage,
            'total_charged' => $totalCharged,
            'payment_method' => 'card',
            'payment_gateway' => 'stripe',
            'stripe_payment_intent_id' => $result['payment_intent_id'],
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'client_secret' => $result['client_secret'],
            'payment_intent_id' => $result['payment_intent_id'],
            'amount' => $totalCharged,
            'currency' => $result['currency'],
            'stripe_publishable_key' => config('platform.payment.stripe.publishable_key'),
            'topup_id' => $topup->id,
        ]);
    }

    public function ledger(Request $request)
    {
        $user = $request->user();
        $ledgers = WalletLedger::whereHas('wallet', fn($q) => $q->where('user_id', $user->id))
            ->with('wallet')
            ->latest()
            ->paginate(50);

        return Inertia::render('Retailer/Wallet/Ledger', compact('ledgers'));
    }
}
