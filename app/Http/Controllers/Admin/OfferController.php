<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferRedemption;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function index(Request $request)
    {
        $stats = OfferRedemption::query()
            ->selectRaw("
                offer_id,
                SUM(status = 'credited') as used,
                COALESCE(SUM(CASE WHEN status = 'credited' THEN benefit_amount END), 0) as paid
            ")
            ->groupBy('offer_id')
            ->get()
            ->keyBy('offer_id');

        $offers = Offer::with(['operators:id,name', 'retailers:id'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Offer $o) use ($stats) {
                $used = (int) ($stats[$o->id]->used ?? 0);
                $status = $o->status();

                if ($status === 'active' && $o->total_limit !== null && $used >= $o->total_limit) {
                    $status = 'exhausted';
                }

                return [
                    'id' => $o->id,
                    'title' => $o->title,
                    'description' => $o->description,
                    'type' => $o->type,
                    'value' => (float) $o->value,
                    'max_cashback' => $o->max_cashback !== null ? (float) $o->max_cashback : null,
                    'min_amount' => (float) $o->min_amount,
                    'max_amount' => $o->max_amount !== null ? (float) $o->max_amount : null,
                    'starts_at' => $o->starts_at?->format('Y-m-d\TH:i'),
                    'expires_at' => $o->expires_at?->format('Y-m-d\TH:i'),
                    'expires_at_iso' => $o->expires_at?->toIso8601String(),
                    'per_retailer_limit' => $o->per_retailer_limit,
                    'total_limit' => $o->total_limit,
                    'audience' => $o->audience,
                    'is_active' => $o->is_active,
                    'status' => $status,
                    'operator_ids' => $o->operators->pluck('id')->all(),
                    'operator_names' => $o->operators->pluck('name')->all(),
                    'retailer_ids' => $o->retailers->pluck('id')->all(),
                    'used' => $used,
                    'paid' => (float) ($stats[$o->id]->paid ?? 0),
                ];
            })
            ->values();

        $data = [
            'offers' => $offers,
            'operators' => Operator::with('country:id,iso_code')
                ->orderBy('name')
                ->get(['id', 'name', 'country_id'])
                ->map(fn (Operator $o) => [
                    'id' => $o->id,
                    'label' => $o->name.($o->country ? " ({$o->country->iso_code})" : ''),
                ]),
            'retailers' => User::where('role', 'retailer')
                ->orderBy('name')
                ->get(['id', 'name', 'shop_name'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'label' => $u->name.($u->shop_name ? " – {$u->shop_name}" : ''),
                ]),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        return Inertia::render('Admin/Offers/Index', $data);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $offer = DB::transaction(function () use ($data, $request) {
            $offer = Offer::create($this->offerFields($data) + ['created_by' => $request->user()->id]);
            $this->syncAudience($offer, $data);

            return $offer;
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $offer->load(['operators:id,name', 'retailers:id'])], 201);
        }

        return back()->with('success', 'Offer created.');
    }

    public function update(Request $request, Offer $offer)
    {
        $data = $this->validated($request, $offer);

        DB::transaction(function () use ($data, $offer) {
            $offer->update($this->offerFields($data));
            $this->syncAudience($offer, $data);
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $offer->fresh()->load(['operators:id,name', 'retailers:id'])]);
        }

        return back()->with('success', 'Offer updated.');
    }

    public function destroy(Request $request, Offer $offer)
    {
        if ($offer->redemptions()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'This offer has been used. Pause it instead of deleting it, so the history is kept.'], 409);
            }

            return back()->with('error', 'This offer has been used. Pause it instead of deleting it, so the history is kept.');
        }

        $offer->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Offer deleted.']);
        }

        return back()->with('success', 'Offer deleted.');
    }

    public function redemptions(Request $request, Offer $offer)
    {
        $rows = OfferRedemption::with(['user:id,name,shop_name', 'transaction:id,receipt_number,mobile_number,operator_id', 'transaction.operator:id,name'])
            ->where('offer_id', $offer->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (OfferRedemption $r) => [
                'id' => $r->id,
                'retailer' => $r->user?->shop_name ?: $r->user?->name,
                'receipt' => $r->transaction?->receipt_number,
                'number' => $r->transaction?->mobile_number,
                'operator' => $r->transaction?->operator?->name,
                'recharge_amount' => (float) $r->recharge_amount,
                'benefit_amount' => (float) $r->benefit_amount,
                'status' => $r->status,
                'at' => $r->created_at?->toIso8601String(),
            ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['redemptions' => $rows]]);
        }

        return response()->json(['redemptions' => $rows]);
    }

    private function offerFields(array $data): array
    {
        $percent = $data['type'] === Offer::TYPE_PERCENT;

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'max_cashback' => $percent ? ($data['max_cashback'] ?? null) : null,
            'min_amount' => $data['min_amount'] ?? 0,
            'max_amount' => $data['max_amount'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'],
            'per_retailer_limit' => $data['per_retailer_limit'] ?? null,
            'total_limit' => $data['total_limit'] ?? null,
            'audience' => $data['audience'],
            'is_active' => $data['is_active'] ?? true,
        ];
    }

    private function syncAudience(Offer $offer, array $data): void
    {
        $offer->operators()->sync($data['operator_ids'] ?? []);
        $offer->retailers()->sync($data['audience'] === 'selected' ? ($data['retailer_ids'] ?? []) : []);
    }

    private function validated(Request $request, ?Offer $offer = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'gt:0'],
            'max_cashback' => ['nullable', 'numeric', 'gt:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date'],
            'per_retailer_limit' => ['nullable', 'integer', 'min:1'],
            'total_limit' => ['nullable', 'integer', 'min:1'],
            'audience' => ['required', 'in:all,selected'],
            'is_active' => ['boolean'],
            'operator_ids' => ['nullable', 'array'],
            'operator_ids.*' => ['integer', 'exists:operators,id'],
            'retailer_ids' => ['nullable', 'array'],
            'retailer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        if ($data['type'] === Offer::TYPE_PERCENT && (float) $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'A percentage cannot be more than 100.']);
        }

        if (isset($data['max_amount']) && (float) $data['max_amount'] < (float) ($data['min_amount'] ?? 0)) {
            throw ValidationException::withMessages(['max_amount' => 'Maximum amount must not be below the minimum amount.']);
        }

        if (! empty($data['starts_at']) && strtotime($data['expires_at']) <= strtotime($data['starts_at'])) {
            throw ValidationException::withMessages(['expires_at' => 'The offer must expire after it starts.']);
        }

        if (! $offer && strtotime($data['expires_at']) <= time()) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry must be in the future.']);
        }

        if ($data['audience'] === 'selected' && empty($data['retailer_ids'])) {
            throw ValidationException::withMessages(['retailer_ids' => 'Choose at least one retailer, or make the offer available to everyone.']);
        }

        return $data;
    }
}
