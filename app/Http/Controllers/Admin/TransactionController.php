<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()->with(['user', 'operator', 'country']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('mobile_number', 'like', "%{$search}%")
                    ->orWhere('receipt_number', 'like', "%{$search}%")
                    ->orWhere('ding_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($retailerId = $request->input('retailer_id')) {
            $query->where('user_id', $retailerId);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $transactions = $query->orderByDesc('created_at')->paginate(50);

        // Stats for the filter page
        $stats = [
            'total' => Transaction::count(),
            'success' => Transaction::where('status', 'success')->count(),
            'failed' => Transaction::where('status', 'failed')->count(),
            'pending' => Transaction::where('status', 'pending')->count(),
            'total_volume' => Transaction::where('status', 'success')->sum('amount'),
        ];

        $retailers = User::where('role', 'retailer')->get(['id', 'name', 'shop_name']);

        return Inertia::render('Admin/Transactions/Index', compact('transactions', 'stats', 'retailers'));
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'operator', 'country', 'admin', 'callbacks']);

        return Inertia::render('Admin/Transactions/Show', compact('transaction'));
    }

    public function refund(Request $request, Transaction $transaction)
    {
        if ($transaction->status !== 'success') {
            return back()->with('error', 'Only successful transactions can be refunded.');
        }

        DB::transaction(function () use ($transaction) {
            $walletService = app(\App\Services\WalletService::class);
            $wallet = $walletService->getWallet($transaction->user);

            $walletService->credit($wallet, (float) ($transaction->retailer_charged ?? 0), 'refund', $transaction->id, "Refund for transaction #{$transaction->receipt_number}");

            $transaction->update([
                'status' => 'refunded',
                'failure_reason' => 'Refunded by admin',
            ]);
        });

        return back()->with('success', 'Transaction refunded successfully!');
    }

    public function export(Request $request)
    {
        $query = Transaction::query()->with(['user', 'operator']);

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transactions = $query->orderByDesc('created_at')->get();

        $filename = 'transactions_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Date', 'Retailer', 'Mobile', 'Operator', 'Country', 'Amount', 'Status', 'Receipt']);

            foreach ($transactions as $txn) {
                fputcsv($handle, [
                    $txn->id,
                    $txn->created_at->format('Y-m-d H:i'),
                    $txn->user->name,
                    $txn->mobile_number,
                    $txn->operator->name,
                    $txn->country->name,
                    $txn->amount,
                    ucfirst($txn->status),
                    $txn->receipt_number,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
