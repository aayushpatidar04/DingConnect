<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionRateController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $rate = DB::transaction(function () use ($data) {
            $rate = CommissionRate::create($this->rateFields($data));
            $this->syncSteps($rate, $data['steps'] ?? []);

            return $rate;
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $rate->load('steps', 'operator:id,name')], 201);
        }

        return redirect()->route('admin.commission-levels.index', ['level' => $rate->commission_level_id])
            ->with('success', 'Rate added.');
    }

    public function update(Request $request, CommissionRate $rate)
    {
        $data = $this->validated($request, $rate);

        DB::transaction(function () use ($data, $rate) {
            $rate->update($this->rateFields($data));
            $this->syncSteps($rate, $data['steps'] ?? []);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $rate->fresh()->load('steps', 'operator:id,name')]);
        }

        return back()->with('success', 'Rate updated.');
    }

    public function destroy(Request $request, CommissionRate $rate)
    {
        $rate->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Rate deleted.']);
        }

        return back()->with('success', 'Rate deleted.');
    }

    private function rateFields(array $data): array
    {
        return [
            'commission_level_id' => $data['commission_level_id'],
            'operator_id' => $data['operator_id'] ?? null,
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'] ?? null,
            'first_topup_amount' => $data['first_topup_amount'],
            'first_bonus_amount' => $data['first_bonus_amount'],
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function syncSteps(CommissionRate $rate, array $steps): void
    {
        $rate->steps()->delete();

        foreach ($steps as $step) {
            $rate->steps()->create([
                'from_sequence' => (int) $step['from_sequence'],
                'to_sequence' => isset($step['to_sequence']) ? (int) $step['to_sequence'] : null,
                'amount' => $step['amount'],
            ]);
        }
    }

    private function validated(Request $request, ?CommissionRate $ignore = null): array
    {
        $data = $request->validate([
            'commission_level_id' => ['required', 'integer', 'exists:commission_levels,id'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'first_topup_amount' => ['required', 'numeric', 'min:0'],
            'first_bonus_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'steps' => ['nullable', 'array', 'max:50'],
            'steps.*.from_sequence' => ['required', 'integer', 'min:2'],
            'steps.*.to_sequence' => ['nullable', 'integer', 'min:2'],
            'steps.*.amount' => ['required', 'numeric', 'min:0'],
        ], [
            'steps.*.from_sequence.min' => 'Ongoing top-ups start from top-up number 2 (the first top-up uses the first top-up amount).',
        ]);

        $min = (float) $data['min_amount'];
        $max = isset($data['max_amount']) ? (float) $data['max_amount'] : null;

        if ($max !== null && $max < $min) {
            throw ValidationException::withMessages(['max_amount' => 'Maximum amount must not be below the minimum amount.']);
        }

        // amount ranges of the same level + operator must not overlap
        $operatorId = $data['operator_id'] ?? null;

        $clashing = CommissionRate::query()
            ->where('commission_level_id', $data['commission_level_id'])
            ->when($operatorId, fn ($q) => $q->where('operator_id', $operatorId), fn ($q) => $q->whereNull('operator_id'))
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->get()
            ->first(function (CommissionRate $other) use ($min, $max) {
                $otherMin = (float) $other->min_amount;
                $otherMax = $other->max_amount !== null ? (float) $other->max_amount : INF;

                return $min <= $otherMax && $otherMin <= ($max ?? INF);
            });

        if ($clashing) {
            throw ValidationException::withMessages([
                'min_amount' => 'This amount range overlaps another rate for the same level and operator.',
            ]);
        }

        // ongoing bands must be consistent and must not overlap each other
        $steps = array_values($data['steps'] ?? []);

        foreach ($steps as $i => $step) {
            if (isset($step['to_sequence']) && (int) $step['to_sequence'] < (int) $step['from_sequence']) {
                throw ValidationException::withMessages(["steps.{$i}.to_sequence" => '"To" must not be below "from".']);
            }
        }

        foreach ($steps as $i => $a) {
            foreach ($steps as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                $aTo = isset($a['to_sequence']) ? (int) $a['to_sequence'] : INF;
                $bTo = isset($b['to_sequence']) ? (int) $b['to_sequence'] : INF;

                if ((int) $a['from_sequence'] <= $bTo && (int) $b['from_sequence'] <= $aTo) {
                    throw ValidationException::withMessages(["steps.{$j}.from_sequence" => 'Ongoing top-up bands must not overlap.']);
                }
            }
        }

        $data['steps'] = $steps;

        return $data;
    }
}
