<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'title', 'description', 'type', 'value', 'max_cashback', 'min_amount', 'max_amount',
        'starts_at', 'expires_at', 'per_retailer_limit', 'total_limit', 'audience', 'is_active', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_cashback' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'per_retailer_limit' => 'integer',
        'total_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(Operator::class, 'offer_operator');
    }

    public function retailers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'offer_retailer');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(OfferRedemption::class);
    }

    /** Switched on, already started and not yet expired. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
    }

    /** What the retailer gets back on a recharge of this amount. */
    public function benefitFor(float $amount): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            $benefit = $amount * (float) $this->value / 100;

            if ($this->max_cashback !== null) {
                $benefit = min($benefit, (float) $this->max_cashback);
            }
        } else {
            $benefit = (float) $this->value;
        }

        return round(max($benefit, 0), 2);
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'paused';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'active';
    }
}
