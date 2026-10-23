<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\CommissionRate;
use App\Models\RetailerCommission;
use App\Models\RetailerCommissionProfile;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommissionController extends Controller
{
    public function index(Request $request, CommissionService $commissions)
    {
        $user = $request->user();

        $profile = RetailerCommissionProfile::where('user_id', $user->id)->first();
        $level = $profile?->commission_level_id ? CommissionLevel::find($profile->commission_level_id) : null;
        $next = $commissions->nextLevelAfter($level);

        $rates = $level
            ? CommissionRate::with(['steps', 'operator:id,name'])
                ->where('commission_level_id', $level->id)
                ->where('is_active', true)
                ->orderBy('min_amount')
                ->get()
                ->sortBy(fn (CommissionRate $r) => strtolower($r->operator?->name ?? ''))
                ->values()
            : [];

        $filters = $request->only('type', 'from', 'to');

        $history = RetailerCommission::query()
            ->with(['operator:id,name', 'transaction:id,receipt_number,mobile_number'])
            ->where('user_id', $user->id)
            ->where('status', 'credited')
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $earned = fn ($query) => (float) $query->sum('commission_amount');
        $base = fn () => RetailerCommission::where('user_id', $user->id)->where('status', 'credited');

        $totals = [
            'today' => $earned($base()->whereDate('created_at', today())),
            'month' => $earned($base()->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)),
            'all' => $earned($base()),
        ];

        return Inertia::render('Retailer/Commissions/Index', [
            'level' => $level ? ['id' => $level->id, 'name' => $level->name, 'min_connections' => $level->min_connections] : null,
            'next' => $next ? ['name' => $next->name, 'min_connections' => $next->min_connections] : null,
            'connections' => [
                'actual' => $profile?->actual_connections ?? 0,
                'level' => $profile?->level_connections ?? 0,
                'auto' => (bool) ($profile?->auto_level_enabled ?? false),
            ],
            'rates' => $rates,
            'commissions' => $history,
            'totals' => $totals,
            'filters' => $filters,
        ]);
    }
}
