<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRedemption extends Model
{
    protected $fillable = [
        'offer_id', 'user_id', 'transaction_id', 'title', 'status',
        'recharge_amount', 'benefit_amount', 'credited_at',
    ];

    protected $casts = [
        'recharge_amount' => 'decimal:2',
        'benefit_amount' => 'decimal:2',
        'credited_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
