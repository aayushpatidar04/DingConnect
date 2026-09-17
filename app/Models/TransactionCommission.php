<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionCommission extends Model
{
    protected $fillable = [
        'transaction_id', 'user_id', 'operator_id', 'amount',
        'commission_rate', 'commission_amount',
    ];

    protected $casts = [
        'amount'            => 'decimal:4',
        'commission_rate'   => 'decimal:2',
        'commission_amount' => 'decimal:4',
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
}
