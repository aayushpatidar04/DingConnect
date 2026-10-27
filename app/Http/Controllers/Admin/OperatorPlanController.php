<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\OperatorPlan;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OperatorPlanController extends Controller
{
    public function index(): Response
    {
        $plans = OperatorPlan::query()
            ->with('operator.country:id,iso_code')
            ->orderBy('operator_id')
            ->orderBy('currency')
            ->orderBy('price')
            ->get()
            ->map(fn (OperatorPlan $plan) => [
                'id' => $plan->id,
                'operator_id' => $plan->operator_id,
                'operator' => $plan->operator?->name,
                'country_iso' => $plan->operator?->country?->iso_code,
                'logo_url' => $plan->operator?->logo_url,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'benefits' => $plan->benefits,
                'is_active' => $plan->is_active,
            ]);

        $operators = Operator::query()
            ->with('country:id,iso_code')
            ->orderBy('name')
            ->get(['id', 'name', 'country_id'])
            ->map(fn (Operator $operator) => [
                'id' => $operator->id,
                'label' => $operator->name.($operator->country ? " ({$operator->country->iso_code})" : ''),
            ]);

        return Inertia::render('Admin/NetworkPlans/Index', compact('plans', 'operators'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        OperatorPlan::query()->create($data);

        return back()->with('success', 'Network plan created.');
    }

    public function update(Request $request, OperatorPlan $operatorPlan): RedirectResponse
    {
        $operatorPlan->update($this->validated($request, $operatorPlan));

        return back()->with('success', 'Network plan updated.');
    }

    public function destroy(OperatorPlan $operatorPlan): RedirectResponse
    {
        $operatorPlan->delete();

        return back()->with('success', 'Network plan deleted.');
    }

    /**
     * @return array{
     *     operator_id: int,
     *     price: numeric-string,
     *     currency: string,
     *     benefits: array<int, string>,
     *     is_active: bool
     * }
     */
    private function validated(Request $request, ?OperatorPlan $operatorPlan = null): array
    {
        return $request->validate([
            'operator_id' => ['required', 'integer', 'exists:operators,id'],
            'price' => [
                'required',
                'numeric',
                'gt:0',
                Rule::unique('operator_plans', 'price')
                    ->where(fn (QueryBuilder $query) => $query
                        ->where('operator_id', $request->input('operator_id'))
                        ->where('currency', $request->input('currency')))
                    ->ignore($operatorPlan?->id),
            ],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'benefits' => ['required', 'array', 'min:1', 'max:20'],
            'benefits.*' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
