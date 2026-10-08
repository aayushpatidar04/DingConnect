<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// app/Models/AllowedNumber.php
class AllowedNumber extends Model
{
    protected $fillable = [
        'user_id', 'mobile', 'serial', 'provider', 'country',
        'note', 'file_name', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(AllowedNumberFile::class, 'file_name', 'name');
    }
}