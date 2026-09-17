<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['name', 'iso_code', 'iso_code_3', 'calling_code', 'currency', 'flag_emoji', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }
}
