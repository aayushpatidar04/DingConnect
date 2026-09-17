<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_retailers' => User::where('role', 'retailer')->count(),
            'active_retailers' => User::where('role', 'retailer')->where('is_active', true)->count(),
            'pending_kyc'     => User::where('role', 'retailer')->where('kyc_status', 'pending')->count(),
            'today_transactions' => Transaction::whereDate('created_at', today())->count(),
            'today_revenue'   => Transaction::whereDate('created_at', today())->where('status', 'success')->sum('amount'),
            'pending_topups'  => WalletTopup::where('status', 'pending')->count(),
            'monthly_revenue' => Transaction::whereMonth('created_at', now()->month)
                ->where('status', 'success')->sum('amount'),
            'total_transactions' => Transaction::count(),
        ];

        $recentTransactions = Transaction::with(['user', 'country', 'operator'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'retailer'       => $t->user?->shop_name ?? $t->user?->name,
                'mobile'         => $t->mobile_number,
                'country'        => $t->country?->name,
                'operator'       => $t->operator?->name,
                'amount'         => $t->amount,
                'status'         => $t->status,
                'created_at'     => $t->created_at?->diffForHumans(),
            ]);

        $chartData = $this->getRevenueChart();

        return Inertia::render('Admin/Dashboard', [
            'stats'     => $stats,
            'transactions' => $recentTransactions,
            'chartData' => $chartData,
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

        return $data->map(fn ($d) => [
            'date'     => $d->date,
            'revenue'  => (float) $d->revenue,
            'count'    => (int) $d->count,
        ])->toArray();
    }
}
