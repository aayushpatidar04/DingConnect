<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\RetailerCommissionProfile;
use App\Models\RetailerLevelChange;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RetailerLevelController extends Controller
{
    public function __construct(protected CommissionService $commissions)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only('search', 'level');

        $retailers = User::where('role', 'retailer')
            ->filter(['search' => $filters['search'] ?? null])
            ->when($filters['level'] ?? null, function ($q, $level) {
                if ($level === 'none') {
                    $q->whereNotIn('id', RetailerCommissionProfile::whereNotNull('commission_level_id')->select('user_id'));
                } else {
                    $q->whereIn('id', RetailerCommissionProfile::where('commission_level_id', $level)->select('user_id'));
                }
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $profiles = RetailerCommissionProfile::with('level:id,name,min_connections')
            ->whereIn('user_id', $retailers->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $levelList = CommissionLevel::orderBy('min_connections')->get(['id', 'name', 'min_connections', 'is_active']);

        $retailers->through(function (User $user) use ($profiles, $levelList) {
            $profile = $profiles->get($user->id);
            $level = $profile?->level;
            $next = $level
                ? $levelList->first(fn ($l) => $l->is_active && $l->min_connections > $level->min_connections)
                : null;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'shop_name' => $user->shop_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_active' => (bool) $user->is_active,
                'level_id' => $level?->id,
                'level_name' => $level?->name,
                'actual_connections' => $profile?->actual_connections ?? 0,
                'level_connections' => $profile?->level_connections ?? 0,
                'commission_topups' => $profile?->commission_topups_count ?? 0,
                'auto_level_enabled' => (bool) ($profile?->auto_level_enabled ?? false),
                'next_level_name' => $next?->name,
                'next_level_min' => $next?->min_connections,
            ];
        });

        return Inertia::render('Admin/Commission/Retailers', [
            'retailers' => $retailers,
            'levels' => $levelList,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, User $retailer)
    {
        abort_unless($retailer->role === 'retailer', 404);

        $data = $request->validate([
            'level_id' => ['nullable', 'integer', 'exists:commission_levels,id'],
            'auto_level_enabled' => ['boolean'],
            'actual_connections' => ['nullable', 'integer', 'min:0'],
            'topups_count' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->commissions->assignLevel(
            $retailer,
            $data['level_id'] ?? null,
            $request->user()->id,
            $data['reason'] ?? null,
            (bool) ($data['auto_level_enabled'] ?? true),
            isset($data['actual_connections']) ? (int) $data['actual_connections'] : null,
            isset($data['topups_count']) ? (int) $data['topups_count'] : null,
        );

        return back()->with('success', "Level updated for {$retailer->name}.");
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'retailer_ids' => ['required', 'array', 'min:1'],
            'retailer_ids.*' => ['integer', 'exists:users,id'],
            'level_id' => ['nullable', 'integer', 'exists:commission_levels,id'],
            'auto_level_enabled' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $retailers = User::where('role', 'retailer')->whereIn('id', $data['retailer_ids'])->get();

        foreach ($retailers as $retailer) {
            $this->commissions->assignLevel(
                $retailer,
                $data['level_id'] ?? null,
                $request->user()->id,
                $data['reason'] ?? 'Bulk assignment',
                (bool) ($data['auto_level_enabled'] ?? true),
            );
        }

        return back()->with('success', $retailers->count() . ' retailer(s) updated.');
    }

    public function history(User $retailer)
    {
        abort_unless($retailer->role === 'retailer', 404);

        $history = RetailerLevelChange::with(['fromLevel:id,name', 'toLevel:id,name', 'admin:id,name'])
            ->where('user_id', $retailer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (RetailerLevelChange $c) => [
                'id' => $c->id,
                'type' => $c->change_type,
                'from' => $c->fromLevel?->name,
                'to' => $c->toLevel?->name,
                'actual_connections' => $c->actual_connections,
                'level_connections_before' => $c->level_connections_before,
                'level_connections_after' => $c->level_connections_after,
                'by' => $c->admin?->name ?? 'System',
                'reason' => $c->reason,
                'at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json(['history' => $history]);
    }
}
