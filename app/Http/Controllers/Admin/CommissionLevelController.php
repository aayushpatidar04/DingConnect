<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\CommissionRate;
use App\Models\Operator;
use App\Models\RetailerCommissionProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CommissionLevelController extends Controller
{
    public function index(Request $request)
    {
        $profileCounts = RetailerCommissionProfile::whereNotNull('commission_level_id')
            ->selectRaw('commission_level_id, COUNT(*) as total')
            ->groupBy('commission_level_id')
            ->pluck('total', 'commission_level_id');

        $levels = CommissionLevel::query()
            ->withCount('rates')
            ->orderBy('min_connections')
            ->get()
            ->map(fn (CommissionLevel $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'min_connections' => $l->min_connections,
                'is_active' => $l->is_active,
                'rates_count' => $l->rates_count,
                'retailers_count' => (int) ($profileCounts[$l->id] ?? 0),
            ])
            ->values();

        $selected = $levels->firstWhere('id', (int) $request->query('level')) ?? $levels->first();

        $rates = $selected
            ? CommissionRate::with(['steps', 'operator:id,name'])
                ->where('commission_level_id', $selected['id'])
                ->orderBy('min_amount')
                ->get()
                ->sortBy(fn (CommissionRate $r) => strtolower($r->operator?->name ?? ''))
                ->values()
            : [];

        $operators = Operator::with('country:id,iso_code')
            ->orderBy('name')
            ->get(['id', 'name', 'country_id'])
            ->map(fn (Operator $o) => [
                'id' => $o->id,
                'label' => $o->name . ($o->country ? " ({$o->country->iso_code})" : ''),
            ]);

        return Inertia::render('Admin/Commission/Levels', [
            'levels' => $levels,
            'selectedLevelId' => $selected['id'] ?? null,
            'rates' => $rates,
            'operators' => $operators,
        ]);
    }

    public function store(Request $request)
    {
        $level = CommissionLevel::create($this->validated($request));

        return redirect()->route('admin.commission-levels.index', ['level' => $level->id])
            ->with('success', 'Level created.');
    }

    public function update(Request $request, CommissionLevel $level)
    {
        $level->update($this->validated($request, $level));

        return back()->with('success', 'Level updated.');
    }

    public function destroy(CommissionLevel $level)
    {
        if (RetailerCommissionProfile::where('commission_level_id', $level->id)->exists()) {
            return back()->with('error', 'Retailers are still on this level. Move them to another level first.');
        }

        $level->delete();

        return redirect()->route('admin.commission-levels.index')->with('success', 'Level deleted.');
    }

    /**
     * Copy every rate (with its ongoing bands) from another level into an empty level.
     */
    public function copyRates(Request $request, CommissionLevel $level)
    {
        $data = $request->validate([
            'source_level_id' => ['required', 'integer', 'exists:commission_levels,id', Rule::notIn([$level->id])],
        ]);

        if ($level->rates()->exists()) {
            return back()->with('error', 'This level already has rates. Delete them first to copy from another level.');
        }

        DB::transaction(function () use ($data, $level) {
            CommissionRate::with('steps')
                ->where('commission_level_id', $data['source_level_id'])
                ->get()
                ->each(function (CommissionRate $source) use ($level) {
                    $copy = $source->replicate(['id', 'created_at', 'updated_at']);
                    $copy->commission_level_id = $level->id;
                    $copy->save();

                    foreach ($source->steps as $step) {
                        $copy->steps()->create($step->only(['from_sequence', 'to_sequence', 'amount']));
                    }
                });
        });

        return back()->with('success', 'Rates copied.');
    }

    private function validated(Request $request, ?CommissionLevel $level = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('commission_levels', 'name')->ignore($level?->id)],
            'min_connections' => ['required', 'integer', 'min:0', Rule::unique('commission_levels', 'min_connections')->ignore($level?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'min_connections.unique' => 'Another level already starts at this number of connections.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? $level?->sort_order ?? 0;

        return $data;
    }
}
