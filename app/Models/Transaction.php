<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'mobile_number', 'operator_id', 'country_id',
        'amount', 'currency', 'ding_transaction_id', 'ding_order_reference',
        'ding_response', 'status', 'failure_reason', 'product_type',
        'ip_address', 'user_agent', 'callback_received', 'callback_received_at',
    ];

    protected $casts = [
        'ding_response'      => 'array',
        'ip_address'         => 'array',
        'callback_received'  => 'boolean',
        'callback_received_at' => 'datetime',
        'amount'             => 'decimal:4',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(TransactionCommission::class);
    }
}
