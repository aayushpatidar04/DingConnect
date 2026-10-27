<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\RetailerConnection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RetailerAnalyticsController extends Controller
{
    private const FILTER_KEYS = [
        'search',
        'network_id',
        'connections',
        'status',
        'kyc_status',
        'city',
        'registered_from',
        'registered_to',
        'activity_from',
        'activity_to',
    ];

    public function index(Request $request): Response|JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'network_id' => ['nullable', 'integer', 'exists:operators,id'],
            'connections' => ['nullable', 'integer', Rule::in([5, 10, 20])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'kyc_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'city' => ['nullable', 'string', 'max:100'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'activity_from' => ['nullable', 'date'],
            'activity_to' => ['nullable', 'date', 'after_or_equal:activity_from'],
        ]);
        $filters += array_fill_keys(self::FILTER_KEYS, null);

        $retailers = $this->filteredRetailers($filters);
        $retailerIds = (clone $retailers)->select('users.id');
        $transactionStats = $this->activityTransactions($retailerIds, $filters)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_transactions")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transactions")
            ->selectRaw("SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as successful_volume")
            ->first();

        $totalTransactions = (int) $transactionStats->total_transactions;
        $successfulTransactions = (int) $transactionStats->successful_transactions;

        $data = [
            'filters' => $filters,
            'operators' => Operator::query()
                ->with('country:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'country_id'])
                ->map(fn (Operator $operator): array => [
                    'id' => $operator->id,
                    'label' => $operator->name.($operator->country ? ' · '.$operator->country->name : ''),
                ]),
            'cities' => User::query()
                ->where('role', 'retailer')
                ->whereNotNull('city')
                ->where('city', '<>', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'stats' => [
                'retailers' => (clone $retailers)->count(),
                'active_retailers' => (clone $retailers)->where('is_active', true)->count(),
                'total_connections' => RetailerConnection::query()
                    ->whereIn('user_id', clone $retailerIds)
                    ->when($filters['network_id'], fn (Builder $query, int $networkId): Builder => $query
                        ->where('operator_id', $networkId))
                    ->count(),
                'total_transactions' => $totalTransactions,
                'successful_transactions' => $successfulTransactions,
                'failed_transactions' => (int) $transactionStats->failed_transactions,
                'successful_volume' => (float) $transactionStats->successful_volume,
                'success_rate' => $totalTransactions > 0
                    ? round(($successfulTransactions / $totalTransactions) * 100, 1)
                    : 0,
            ],
            'connectionDistribution' => [
                ['label' => '0 connections', 'count' => $this->connectionBandCount(clone $retailers, $filters, null)],
                ['label' => '1–4 connections', 'count' => $this->connectionBandCount(clone $retailers, $filters, 1, 4)],
                ['label' => '5–9 connections', 'count' => $this->connectionBandCount(clone $retailers, $filters, 5, 9)],
                ['label' => '10–19 connections', 'count' => $this->connectionBandCount(clone $retailers, $filters, 10, 19)],
                ['label' => '20+ connections', 'count' => $this->connectionBandCount(clone $retailers, $filters, 20)],
            ],
            'networkBreakdown' => RetailerConnection::query()
                ->join('operators', 'operators.id', '=', 'retailer_connections.operator_id')
                ->whereIn('retailer_connections.user_id', clone $retailerIds)
                ->when($filters['network_id'], fn (Builder $query, int $networkId): Builder => $query
                    ->where('retailer_connections.operator_id', $networkId))
                ->selectRaw('operators.id, operators.name, COUNT(*) as connections')
                ->groupBy('operators.id', 'operators.name')
                ->orderByDesc('connections')
                ->orderBy('operators.name')
                ->get()
                ->map(fn (object $network): array => [
                    'id' => (int) $network->id,
                    'name' => $network->name,
                    'connections' => (int) $network->connections,
                ]),
            'highestRetailers' => $this->connectionReport(clone $retailers, $filters, true),
            'lowestRetailers' => $this->connectionReport(clone $retailers, $filters, false),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        return Inertia::render('Admin/RetailerAnalytics/Index', $data);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredRetailers(array $filters): Builder
    {
        return User::query()
            ->where('role', 'retailer')
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('shop_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('postcode', 'like', "%{$search}%");
                });
            })
            ->when($filters['network_id'], fn (Builder $query, int $networkId): Builder => $query
                ->whereHas('retailerConnections', fn (Builder $connections): Builder => $connections->where('operator_id', $networkId)))
            ->when($filters['connections'], function (Builder $query, int $minimum) use ($filters): void {
                $query->whereHas(
                    'retailerConnections',
                    fn (Builder $connections): Builder => $this->applyNetworkFilter($connections, $filters),
                    '>=',
                    $minimum,
                );
            })
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query
                ->where('is_active', $status === 'active'))
            ->when($filters['kyc_status'], fn (Builder $query, string $status): Builder => $query
                ->where('kyc_status', $status))
            ->when($filters['city'], fn (Builder $query, string $city): Builder => $query
                ->where('city', $city))
            ->when($filters['registered_from'], fn (Builder $query, string $date): Builder => $query
                ->whereDate('created_at', '>=', $date))
            ->when($filters['registered_to'], fn (Builder $query, string $date): Builder => $query
                ->whereDate('created_at', '<=', $date));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function connectionReport(Builder $query, array $filters, bool $highestFirst): array
    {
        $query
            ->withCount([
                'retailerConnections' => fn (Builder $connections): Builder => $this->applyNetworkFilter($connections, $filters),
            ])
            ->withCount([
                'transactions as transactions_count' => fn (Builder $transactions): Builder => $this->applyActivityDates($transactions, $filters),
                'transactions as successful_transactions_count' => fn (Builder $transactions): Builder => $this->applyActivityDates($transactions, $filters)
                    ->where('status', 'success'),
            ])
            ->withSum([
                'transactions as successful_volume' => fn (Builder $transactions): Builder => $this->applyActivityDates($transactions, $filters)
                    ->where('status', 'success'),
            ], 'amount');

        if ($highestFirst) {
            $query->orderByDesc('retailer_connections_count');
        } else {
            $query->orderBy('retailer_connections_count');
        }

        return $query
            ->orderBy('users.id')
            ->limit(10)
            ->get([
                'users.id',
                'users.name',
                'users.shop_name',
                'users.email',
                'users.city',
                'users.is_active',
                'users.kyc_status',
                'users.created_at',
            ])
            ->map(fn (User $retailer): array => [
                'id' => $retailer->id,
                'name' => $retailer->name,
                'shop_name' => $retailer->shop_name,
                'email' => $retailer->email,
                'city' => $retailer->city,
                'is_active' => $retailer->is_active,
                'kyc_status' => $retailer->kyc_status,
                'created_at' => $retailer->created_at?->toDateString(),
                'connections' => (int) $retailer->retailer_connections_count,
                'transactions' => (int) $retailer->transactions_count,
                'successful_transactions' => (int) $retailer->successful_transactions_count,
                'successful_volume' => (float) $retailer->successful_volume,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function activityTransactions(Builder $retailers, array $filters): Builder
    {
        return $this->applyActivityDates(
            Transaction::query()->whereIn('user_id', $retailers),
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyActivityDates(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['activity_from'], fn (Builder $query, string $date): Builder => $query
                ->whereDate('created_at', '>=', $date))
            ->when($filters['activity_to'], fn (Builder $query, string $date): Builder => $query
                ->whereDate('created_at', '<=', $date));
    }

    private function connectionBandCount(Builder $query, array $filters, ?int $minimum, ?int $maximum = null): int
    {
        if ($minimum === null) {
            return $query
                ->whereDoesntHave('retailerConnections', fn (Builder $connections): Builder => $this->applyNetworkFilter($connections, $filters))
                ->count();
        }

        $query->whereHas(
            'retailerConnections',
            fn (Builder $connections): Builder => $this->applyNetworkFilter($connections, $filters),
            '>=',
            $minimum,
        );

        if ($maximum !== null) {
            $query->whereHas(
                'retailerConnections',
                fn (Builder $connections): Builder => $this->applyNetworkFilter($connections, $filters),
                '<=',
                $maximum,
            );
        }

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyNetworkFilter(Builder $connections, array $filters): Builder
    {
        return $connections->when($filters['network_id'], fn (Builder $query, int $networkId): Builder => $query
            ->where('operator_id', $networkId));
    }
}
