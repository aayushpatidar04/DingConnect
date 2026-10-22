<?php

namespace App\Http\Controllers\Api\Retailer;

use App\Http\Controllers\Controller;
use App\Models\WalletLedger;
use App\Models\WalletTopup;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function topUpDetails(Request $request, WalletService $walletService): JsonResponse
    {
        $wallet = $walletService->getWallet($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
                'available_balance' => $walletService->getAvailableBalance($wallet),
                'stripe_publishable_key' => config('platform.payment.stripe.publishable_key'),
            ],
        ]);
    }

    public function index(Request $request, WalletService $walletService): JsonResponse
    {
        $wallet = $walletService->getWallet($request->user());
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,completed,failed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $topups = WalletTopup::where('user_id', $request->user()->id)
            ->select([
                'id',
                'user_id',
                'amount',
                'currency',
                'fee_amount',
                'fee_percentage',
                'total_charged',
                'payment_method',
                'payment_gateway',
                'status',
                'receipt_number',
                'created_at',
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
                'available_balance' => $walletService->getAvailableBalance($wallet),
                'topups' => $topups,
            ],
        ]);
    }

    public function ledger(Request $request, WalletService $walletService): JsonResponse
    {
        $wallet = $walletService->getWallet($request->user());
        $ledger = WalletLedger::where('wallet_id', $wallet->id)
            ->latest()
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $ledger]);
    }
}
