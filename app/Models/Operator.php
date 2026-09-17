<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operator extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'provider_code',
        'country_id',
        'logo_url',
        'is_active',
        'display_order',
        'region_codes',
        'payment_types',
        'validation_regex',
        'customer_care_number',
        'is_premium',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
