<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimOrderItem extends Model
{
    protected $fillable = [
        'sim_order_id',
        'operator_id',
        'sim_order_product_id',
        'category',
        'unit_price',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function simOrder(): BelongsTo
    {
        return $this->belongsTo(SimOrder::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SimOrderProduct::class, 'sim_order_product_id');
    }
}
