<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetailerCommission extends Model
{
    protected $fillable = [
        'transaction_id', 'user_id', 'retailer_connection_id', 'commission_level_id',
        'commission_rate_id', 'operator_id', 'type', 'topup_sequence', 'recharge_amount',
        'topup_amount', 'bonus_amount', 'commission_amount', 'status', 'skip_reason', 'credited_at',
    ];

    protected $casts = [
        'recharge_amount' => 'decimal:2',
        'topup_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'credited_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CommissionLevel::class, 'commission_level_id');
    }

    public function scopeFilter(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['search'] ?? null, function ($q, $s) {
                $q->whereHas('transaction', function ($q) use ($s) {
                    $q->where('mobile_number', 'like', "%{$s}%")
                        ->orWhere('receipt_number', 'like', "%{$s}%");
                });
            })
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($f['retailer_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['operator_id'] ?? null, fn ($q, $v) => $q->where('operator_id', $v))
            ->when($f['level_id'] ?? null, fn ($q, $v) => $q->where('commission_level_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
