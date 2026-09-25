<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'user_id', 'mobile_number', 'serial_number', 'operator_id', 'country_id',
        'amount', 'currency', 'ding_transaction_id', 'ding_order_reference',
        'ding_response', 'status', 'failure_reason', 'product_type',
        'ip_address', 'user_agent', 'callback_received', 'callback_received_at',
        'receipt_number', 'redemption_type', 'redemption_reference',
        'sku_code', 'send_value', 'receive_value', 'send_currency', 'receive_currency',
        'display_text', 'receipt_text', 'validity_period', 'benefits',
        'region_code', 'provider_code', 'free_range', 'receive_value_excluding_tax',
        'description_markdown', 'readmore_markdown',
    ];

    protected $casts = [
        'ding_response'              => 'array',
        'benefits'                   => 'array',
        'ip_address'                 => 'string',
        'callback_received'          => 'boolean',
        'callback_received_at'       => 'datetime',
        'amount'                     => 'decimal:4',
        'created_at'                 => 'datetime',
        'updated_at'                 => 'datetime',
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
