<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
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

        $filters = $request->only('status', 'type', 'from', 'to');

        $topups = WalletTopup::query()
            ->where('user_id', $user->id)
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, function ($q, $type) {
                match ($type) {
                    'card', 'bank_transfer' => $q->where('payment_method', $type),
                    'commission', 'bonus', 'offer', 'admin' => $q->where('payment_gateway', $type),
                    default => null,
                };
            })
            ->when($filters['from'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString(); // keeps filters in the pagination links

        // Receipt numbers of the recharges that commission / offer entries belong to
        $rechargeIds = $topups->getCollection()
            ->filter(fn(WalletTopup $t) => $this->isSystemEntry($t))
            ->map(fn(WalletTopup $t) => data_get($t->payment_response, 'recharge_transaction_id'))
            ->filter()
            ->unique();

        $recharges = Transaction::where('user_id', $user->id)
            ->whereIn('id', $rechargeIds)
            ->pluck('receipt_number', 'id');

        $topups->through(fn(WalletTopup $t) => $this->present($t, $recharges));

        // All-time totals of completed entries
        $totals = WalletTopup::where('user_id', $user->id)
            ->where('status', 'completed')
            ->selectRaw("
                SUM(CASE WHEN payment_method IN ('card', 'bank_transfer') THEN amount ELSE 0 END) as deposits,
                SUM(CASE WHEN payment_method IN ('card', 'bank_transfer') THEN fee_amount ELSE 0 END) as fees,
                SUM(CASE WHEN payment_gateway = 'commission' THEN amount ELSE 0 END) as commission,
                SUM(CASE WHEN payment_gateway = 'bonus' THEN amount ELSE 0 END) as bonus,
                SUM(CASE WHEN payment_gateway = 'offer' THEN amount ELSE 0 END) as offers,
                SUM(CASE WHEN payment_gateway = 'admin' THEN amount ELSE 0 END) as admin
            ")
            ->first();

        $summary = [
            'deposits' => (float) $totals->deposits,
            'fees' => (float) $totals->fees,
            'commission' => (float) $totals->commission,
            'bonus' => (float) $totals->bonus,
            'offers' => (float) $totals->offers,
            'admin' => (float) $totals->admin,
        ];

        return Inertia::render('Retailer/Wallet/Index', compact('wallet', 'availableBalance', 'topups', 'filters', 'summary'));
    }

    /** Entries created by the system (commission, bonus, offer cashback, admin credit). */
    private function isSystemEntry(WalletTopup $topup): bool
    {
        return in_array($topup->payment_gateway, [
            WalletTopup::GATEWAY_COMMISSION,
            WalletTopup::GATEWAY_BONUS,
            WalletTopup::GATEWAY_OFFER,
            WalletTopup::GATEWAY_ADMIN,
        ], true);
    }

    /**
     * The fields the wallet page shows. The raw gateway response is not sent to the browser.
     */
    private function present(WalletTopup $topup, $recharges): array
    {
        $system = $this->isSystemEntry($topup);
        $kind = $system ? $topup->payment_gateway : ($topup->payment_method === 'bank_transfer' ? 'bank_transfer' : 'card');
        $r = $system && is_array($topup->payment_response) ? $topup->payment_response : [];
        $rechargeId = $r['recharge_transaction_id'] ?? null;

        return [
            'id' => $topup->id,
            'kind' => $kind,
            'status' => $topup->status,
            'amount' => (float) $topup->amount,
            'currency' => $topup->currency,
            'fee_amount' => (float) $topup->fee_amount,
            'fee_percentage' => (float) $topup->fee_percentage,
            'total_charged' => (float) $topup->total_charged,
            'receipt_number' => $topup->receipt_number,
            'reference' => $system ? null : ($topup->stripe_payment_intent_id ?: $topup->gateway_transaction_id ?: $topup->gateway_order_id),
            'created_at' => $topup->created_at?->toIso8601String(),
            'updated_at' => $topup->updated_at?->toIso8601String(),
            'details' => $system ? [
                'type' => $r['type'] ?? null,           // first | ongoing (commission and bonus)
                'sequence' => $r['sequence'] ?? null,
                'level' => $r['level'] ?? null,
                'operator' => $r['operator'] ?? null,
                'number' => $r['number'] ?? null,
                'offer_title' => $topup->payment_gateway === WalletTopup::GATEWAY_OFFER ? ($r['title'] ?? null) : null,
                'description' => $r['description'] ?? null, // admin credit note
                'recharge_id' => $rechargeId && isset($recharges[$rechargeId]) ? (int) $rechargeId : null,
                'recharge_receipt' => $rechargeId ? ($recharges[$rechargeId] ?? null) : null,
            ] : null,
        ];
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
