<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\DingConnectService;
use App\Services\ValuetopupService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function admin(DingConnectService $ding, ValuetopupService $valuetopup): JsonResponse
    {
        $totalTransactions = Transaction::count();
        $successTransactions = Transaction::where('status', 'success')->count();
        $stats = [
            'total_retailers' => User::where('role', 'retailer')->count(),
            'active_retailers' => User::where('role', 'retailer')->where('is_active', true)->count(),
            'pending_kyc' => User::where('role', 'retailer')->where('kyc_status', 'pending')->count(),
            'today_transactions' => Transaction::whereDate('created_at', today())->count(),
            'today_success' => Transaction::whereDate('created_at', today())->where('status', 'success')->count(),
            'today_volume' => (float) Transaction::whereDate('created_at', today())->where('status', 'success')->sum('amount'),
            'success_rate' => $totalTransactions > 0 ? round(($successTransactions / $totalTransactions) * 100, 1) : 0,
            'pending_topups' => WalletTopup::where('status', 'pending')->count(),
            'monthly_revenue' => (float) Transaction::whereMonth('created_at', now()->month)->where('status', 'success')->sum('amount'),
            'total_transactions' => $totalTransactions,
            'ding_balance' => $this->dingBalance($ding),
            'prepay_nation_balance' => $this->valuetopupBalance($valuetopup),
        ];

        $topRetailers = User::where('role', 'retailer')
            ->select('id', 'shop_name', 'name', 'phone')
            ->withCount([
                'transactions as month_transactions' => fn ($query) => $query->whereMonth('created_at', now()->month),
                'transactions as month_success' => fn ($query) => $query->whereMonth('created_at', now()->month)->where('status', 'success'),
            ])
            ->orderByDesc('month_success')
            ->orderByDesc('month_transactions')
            ->limit(5)
            ->get();

        $recentTransactions = Transaction::with(['user', 'country', 'operator'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'retailer' => $transaction->user?->shop_name ?? $transaction->user?->name,
                'mobile' => $transaction->mobile_number,
                'country' => $transaction->country?->name,
                'operator' => $transaction->operator?->name,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at?->diffForHumans(),
            ]);

        $chartData = DB::table('transactions')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue, COUNT(*) as count')
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $row->date,
                'revenue' => (float) $row->revenue,
                'count' => (int) $row->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => compact('stats', 'topRetailers', 'recentTransactions', 'chartData'),
        ]);
    }

    public function retailer(Request $request, WalletService $walletService): JsonResponse
    {
        $user = $request->user();
        $wallet = $walletService->getWallet($user);
        $availableBalance = $walletService->getAvailableBalance($wallet);
        $transactions = Transaction::whereBelongsTo($user);
        $totalTransactions = (clone $transactions)->count();
        $successfulTransactions = (clone $transactions)->where('status', 'success')->count();
        $settledTransactions = (clone $transactions)->whereIn('status', ['success', 'failed', 'cancelled'])->count();

        $stats = [
            'wallet_balance' => (float) $wallet->balance,
            'available_balance' => (float) $availableBalance,
            'locked_balance' => (float) ($wallet->balance - $availableBalance),
            'today_transactions' => (clone $transactions)->whereDate('created_at', today())->count(),
            'today_success' => (clone $transactions)->whereDate('created_at', today())->where('status', 'success')->count(),
            'today_failed' => (clone $transactions)->whereDate('created_at', today())->where('status', 'failed')->count(),
            'this_month_transactions' => (clone $transactions)->whereMonth('created_at', now()->month)->count(),
            'this_month_success' => (clone $transactions)->whereMonth('created_at', now()->month)->where('status', 'success')->count(),
            'this_month_volume' => (float) (clone $transactions)->whereMonth('created_at', now()->month)->where('status', 'success')->sum('amount'),
            'total_transactions' => $totalTransactions,
            'total_success' => $successfulTransactions,
            'success_rate' => $settledTransactions > 0 ? round(($successfulTransactions / $settledTransactions) * 100, 1) : 0,
            'low_balance' => (float) $availableBalance < config('platform.pricing.low_balance_threshold', 10),
        ];

        $recentTransactions = (clone $transactions)
            ->with(['operator:id,name', 'country:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'mobile_number' => $transaction->mobile_number,
                'amount' => (float) $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at?->diffForHumans(),
                'operator' => $transaction->operator ? ['name' => $transaction->operator->name] : null,
            ]);

        $dailyTransactions = (clone $transactions)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as amount', ['success'])
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->groupBy('date')
            ->get()
            ->keyBy('date');
        $chartData = collect(range(6, 0))->map(function (int $daysAgo) use ($dailyTransactions): array {
            $date = now()->subDays($daysAgo)->toDateString();
            $row = $dailyTransactions->get($date);

            return ['date' => $date, 'count' => (int) ($row->count ?? 0), 'amount' => (float) ($row->amount ?? 0)];
        });

        return response()->json([
            'success' => true,
            'data' => compact('stats', 'recentTransactions', 'chartData'),
        ]);
    }

    private function dingBalance(DingConnectService $ding): array
    {
        try {
            $result = $ding->getBalance();
            $data = $result['data'] ?? $result;

            if (($data['ResultCode'] ?? 0) !== 1) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'balance' => number_format((float) ($data['Balance'] ?? 0), 2),
                'currency' => $data['CurrencyIso'] ?? 'GBP',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Ding balance fetch failed: '.$exception->getMessage());

            return ['success' => false];
        }
    }

    private function valuetopupBalance(ValuetopupService $valuetopup): array
    {
        try {
            $result = $valuetopup->balance();
            $data = $result['data'] ?? $result;
            $balance = $data['payLoad']['balance'] ?? null;

            if (($data['responseCode'] ?? null) !== '000' || ! is_numeric($balance)) {
                return ['success' => false];
            }

            return ['success' => true, 'balance' => number_format((float) $balance, 2), 'currency' => 'GBP'];
        } catch (\Throwable $exception) {
            Log::warning('Prepay Nation balance fetch failed: '.$exception->getMessage());

            return ['success' => false];
        }
    }
}
