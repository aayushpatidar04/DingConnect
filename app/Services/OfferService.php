<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferRedemption;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferService
{
    /** A reserved use only counts while its recharge is still alive or has succeeded. */
    private const COUNTED_TRANSACTION_STATUSES = ['pending', 'processing', 'success'];

    public function __construct(protected WalletService $wallets)
    {
    }

    // =========================================================================
    // USAGE
    // =========================================================================

    protected function usageQuery(Offer $offer): Builder
    {
        return OfferRedemption::where('offer_id', $offer->id)
            ->where(function ($q) {
                $q->where('status', 'credited')
                    ->orWhere(function ($q) {
                        $q->where('status', 'pending')
                            ->whereHas('transaction', fn ($t) => $t->whereIn('status', self::COUNTED_TRANSACTION_STATUSES));
                    });
            });
    }

    public function usedBy(Offer $offer, int $userId): int
    {
        return $this->usageQuery($offer)->where('user_id', $userId)->count();
    }

    public function usedTotal(Offer $offer): int
    {
        return $this->usageQuery($offer)->count();
    }

    /** Uses this retailer has left on the offer, or null when unlimited. */
    public function remainingFor(Offer $offer, User $user): ?int
    {
        $remaining = [];

        if ($offer->per_retailer_limit !== null) {
            $remaining[] = $offer->per_retailer_limit - $this->usedBy($offer, $user->id);
        }

        if ($offer->total_limit !== null) {
            $remaining[] = $offer->total_limit - $this->usedTotal($offer);
        }

        return $remaining ? max(min($remaining), 0) : null;
    }

    // =========================================================================
    // WHO SEES WHAT
    // =========================================================================

    /** Live offers this retailer is allowed to see. */
    protected function visibleQuery(User $user): Builder
    {
        return Offer::live()->where(function ($q) use ($user) {
            $q->where('audience', 'all')
                ->orWhere(function ($q) use ($user) {
                    $q->where('audience', 'selected')
                        ->whereHas('retailers', fn ($r) => $r->where('users.id', $user->id));
                });
        });
    }

    /**
     * Offers that can be used on a recharge of this amount for this provider:
     * live, visible to the retailer, matching provider and amount range, with uses left.
     */
    public function eligibleFor(User $user, ?int $operatorId, float $amount): Collection
    {
        return $this->visibleQuery($user)
            ->with('operators:id,name')
            ->where(function ($q) use ($operatorId) {
                $q->whereDoesntHave('operators');

                if ($operatorId) {
                    $q->orWhereHas('operators', fn ($o) => $o->where('operators.id', $operatorId));
                }
            })
            ->where('min_amount', '<=', $amount)
            ->where(function ($q) use ($amount) {
                $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount);
            })
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (Offer $offer) => $this->remainingFor($offer, $user) !== 0)
            ->values();
    }

    /** Everything the retailer can currently use, for the dashboard. */
    public function activeFor(User $user): array
    {
        return $this->visibleQuery($user)
            ->with('operators:id,name')
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (Offer $offer) => $this->remainingFor($offer, $user) !== 0)
            ->map(fn (Offer $offer) => $this->present($offer, $user))
            ->values()
            ->all();
    }

    public function present(Offer $offer, User $user, ?float $amount = null): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'type' => $offer->type,
            'value' => (float) $offer->value,
            'max_cashback' => $offer->max_cashback !== null ? (float) $offer->max_cashback : null,
            'min_amount' => (float) $offer->min_amount,
            'max_amount' => $offer->max_amount !== null ? (float) $offer->max_amount : null,
            'expires_at' => $offer->expires_at?->toIso8601String(),
            'providers' => $offer->operators->pluck('name')->values()->all(), // empty = all providers
            'remaining' => $this->remainingFor($offer, $user),
            'benefit' => $amount !== null ? $offer->benefitFor($amount) : null,
        ];
    }

    // =========================================================================
    // REDEMPTION
    // =========================================================================

    /**
     * Reserve one use of an offer for a recharge. Call it inside the database transaction that
     * creates the recharge: the offer row is locked, so limits cannot be beaten by two requests
     * at once, and a failure here rolls the whole recharge back.
     */
    public function reserve(User $user, int $offerId, Transaction $transaction): OfferRedemption
    {
        $offer = Offer::lockForUpdate()->find($offerId);

        $eligible = $offer
            ? $this->eligibleFor($user, $transaction->operator_id ? (int) $transaction->operator_id : null, (float) $transaction->amount)
                ->firstWhere('id', $offer->id)
            : null;

        if (! $eligible) {
            throw ValidationException::withMessages([
                'offer_id' => 'This offer is no longer available for this recharge. Please choose again.',
            ]);
        }

        return OfferRedemption::create([
            'offer_id' => $offer->id,
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'title' => $offer->title,
            'status' => 'pending',
            'recharge_amount' => (float) $transaction->amount,
            'benefit_amount' => $offer->benefitFor((float) $transaction->amount),
        ]);
    }

    /**
     * Pay the cashback for a successful recharge into the wallet, as an entry in the retailer's
     * wallet top-ups list. Safe to call more than once: only a pending redemption is paid.
     */
    public function settle(Transaction $transaction): ?OfferRedemption
    {
        $transaction = $transaction->fresh();

        if (! $transaction || $transaction->status !== 'success') {
            return null;
        }

        return DB::transaction(function () use ($transaction) {
            $redemption = OfferRedemption::where('transaction_id', $transaction->id)->lockForUpdate()->first();

            if (! $redemption || $redemption->status !== 'pending') {
                return null;
            }

            $benefit = round((float) $redemption->benefit_amount, 2);

            if ($benefit <= 0) {
                $redemption->update(['status' => 'void']);

                return $redemption;
            }

            $entry = WalletTopup::create([
                'user_id' => $transaction->user_id,
                'amount' => $benefit,
                'currency' => config('platform.wallet.currency', 'GBP'),
                'fee_amount' => 0,
                'fee_percentage' => 0,
                'total_charged' => $benefit,
                'payment_method' => 'wallet',
                'payment_gateway' => WalletTopup::GATEWAY_OFFER,
                'gateway_order_id' => "offer-{$transaction->id}", // unique: one payout per recharge
                'payment_response' => [
                    'source' => 'offer',
                    'offer_id' => $redemption->offer_id,
                    'title' => $redemption->title,
                    'operator' => $transaction->operator?->name,
                    'number' => $transaction->mobile_number,
                    'recharge_transaction_id' => $transaction->id,
                ],
                'status' => 'completed',
            ]);

            $this->wallets->credit(
                (int) $transaction->user_id,
                $benefit,
                $entry->id,
                sprintf('Offer cashback - %s (%s %s)', $redemption->title, $transaction->operator?->name ?? 'operator', $transaction->mobile_number)
            );

            $redemption->update(['status' => 'credited', 'credited_at' => now()]);

            return $redemption;
        });
    }

    /** The recharge failed: give the use back. */
    public function release(Transaction $transaction): void
    {
        OfferRedemption::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->update(['status' => 'void']);
    }
}
