<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;


class TransactionController extends Controller
{
    private const FILTER_KEYS = ['search', 'status', 'retailer_id', 'operator_id', 'from', 'to'];

    public function index(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);

        $transactions = Transaction::query()
            ->with(['user:id,name,shop_name', 'operator:id,name', 'country:id,name'])
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString(); // keeps filters in the pagination links

        // Platform-wide stats (unchanged behaviour), computed in one query instead of five
        $row = Transaction::selectRaw("
        COUNT(*) as total,
        SUM(status = 'success') as success,
        SUM(status = 'failed') as failed,
        SUM(status = 'pending') as pending,
        COALESCE(SUM(CASE WHEN status = 'success' THEN amount END), 0) as total_volume
    ")->first();

        $stats = [
            'total' => (int) $row->total,
            'success' => (int) $row->success,
            'failed' => (int) $row->failed,
            'pending' => (int) $row->pending,
            'total_volume' => (float) $row->total_volume,
        ];

        $retailers = User::where('role', 'retailer')
            ->orderBy('name')
            ->get(['id', 'name', 'shop_name']);

        $operators = Operator::with('country:id,iso_code')
            ->orderBy('name')
            ->get(['id', 'name', 'country_id']);

        return Inertia::render('Admin/Transactions/Index', compact(
            'transactions',
            'stats',
            'retailers',
            'operators',
            'filters'
        ));
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
        return Excel::download(
            new TransactionsExport($request->only(self::FILTER_KEYS)),
            'transactions_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
