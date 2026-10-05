<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\DingConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, DingConnectService $dingService)
    {
        $totalTxns = Transaction::count();
        $successTxns = Transaction::where('status', 'success')->count();

        $stats = [
            'total_retailers' => User::where('role', 'retailer')->count(),
            'active_retailers' => User::where('role', 'retailer')->where('is_active', true)->count(),
            'pending_kyc' => User::where('role', 'retailer')->where('kyc_status', 'pending')->count(),
            'today_transactions' => Transaction::whereDate('created_at', today())->count(),
            'today_success' => Transaction::whereDate('created_at', today())->where('status', 'success')->count(),
            'today_volume' => Transaction::whereDate('created_at', today())->where('status', 'success')->sum('amount'),
            'success_rate' => $totalTxns > 0 ? round(($successTxns / $totalTxns) * 100, 1) : 0,
            'pending_topups' => WalletTopup::where('status', 'pending')->count(),
            'monthly_revenue' => Transaction::whereMonth('created_at', now()->month)->where('status', 'success')->sum('amount'),
            'total_transactions' => $totalTxns,
            'ding_balance' => $this->getDingBalance($dingService),
        ];

        $topRetailers = User::where('role', 'retailer')
            ->select('id', 'shop_name', 'name', 'phone')
            ->withCount([
                'transactions as month_transactions' => fn($q) =>
                    $q->whereMonth('created_at', now()->month),
                'transactions as month_success' => fn($q) =>
                    $q->whereMonth('created_at', now()->month)
                        ->where('status', 'success'),
            ])
            ->orderByDesc('month_success')   // ranked by successful transactions
            ->orderByDesc('month_transactions') // tiebreak on total
            ->limit(5)
            ->get();

        $recentTransactions = Transaction::with(['user', 'country', 'operator'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'retailer' => $t->user?->shop_name ?? $t->user?->name,
                'mobile' => $t->mobile_number,
                'country' => $t->country?->name,
                'operator' => $t->operator?->name,
                'amount' => (float) $t->amount,
                'status' => $t->status,
                'created_at' => $t->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'topRetailers' => $topRetailers,
            'recentTransactions' => $recentTransactions,
            'chartData' => $this->getRevenueChart(),
        ]);
    }

    private function getRevenueChart(): array
    {
        $data = DB::table('transactions')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $data->map(fn($d) => [
            'date' => $d->date,
            'revenue' => (float) $d->revenue,
            'count' => (int) $d->count,
        ])->toArray();
    }

    private function getDingBalance(DingConnectService $dingService): array
    {
        try {
            $result = $dingService->getBalance();
            $data = $result['data'] ?? $result; // covers both wrapper styles

            if (($data['ResultCode'] ?? 0) !== 1) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'balance' => number_format((float) ($data['Balance'] ?? 0), 2),
                'currency' => $data['CurrencyIso'] ?? 'GBP',
            ];
        } catch (\Throwable $e) {
            Log::warning('Ding balance fetch failed: ' . $e->getMessage());
            return ['success' => false]; // frontend shows "N/A"
        }
    }
}
