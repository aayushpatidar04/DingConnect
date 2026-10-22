<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'retailer_id' => ['nullable', 'integer', 'exists:users,id'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $transactions = Transaction::with(['user:id,name,shop_name', 'operator:id,name', 'country:id,name'])
            ->filter($filters)
            ->latest()
            ->paginate(50);
        $stats = [
            'total' => Transaction::count(),
            'success' => Transaction::where('status', 'success')->count(),
            'failed' => Transaction::where('status', 'failed')->count(),
            'pending' => Transaction::where('status', 'pending')->count(),
            'total_volume' => (float) Transaction::where('status', 'success')->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'stats' => $stats,
                'retailers' => User::where('role', 'retailer')->orderBy('name')->get(['id', 'name', 'shop_name']),
                'operators' => Operator::with('country:id,iso_code')->orderBy('name')->get(['id', 'name', 'country_id']),
                'filters' => $filters,
            ],
        ]);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['user', 'operator', 'country']);

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    public function refund(Transaction $transaction, WalletService $walletService): JsonResponse
    {
        if ($transaction->status !== 'success') {
            return response()->json(['success' => false, 'message' => 'Only successful transactions can be refunded.'], 409);
        }

        DB::transaction(function () use ($transaction, $walletService): void {
            $walletService->getWallet($transaction->user);
            $walletService->refund(
                $transaction->user_id,
                (float) $transaction->amount,
                $transaction->id,
                "Refund for transaction #{$transaction->receipt_number}",
            );
            $transaction->update(['status' => 'refunded', 'failure_reason' => 'Refunded by admin']);
        });

        return response()->json(['success' => true, 'data' => $transaction->fresh()]);
    }
}
