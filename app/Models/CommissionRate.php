<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRate extends Model
{
    protected $fillable = [
        'commission_level_id', 'operator_id', 'min_amount', 'max_amount',
        'first_topup_amount', 'first_bonus_amount', 'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'first_topup_amount' => 'decimal:2',
        'first_bonus_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(CommissionLevel::class, 'commission_level_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(CommissionRateStep::class)->orderBy('from_sequence');
    }
}
