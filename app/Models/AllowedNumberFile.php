<?php
// app/Models/AllowedNumberFile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllowedNumberFile extends Model
{
    protected $fillable = [
        'user_id', 'name', 'path', 'total_rows', 'imported_rows', 'skipped_rows',
    ];

    public function numbers(): HasMany
    {
        return $this->hasMany(AllowedNumber::class, 'file_name', 'name');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}