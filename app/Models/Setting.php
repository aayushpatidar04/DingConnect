<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Retrieve a setting by key with an optional default.
     */
    public static function get(string $key, $default = null)
    {
        try {
            $record = static::where('key', $key)->first();
            if (!$record) {
                return $default;
            }
            $val = $record->value;

            if (is_array($val)) {
                return $val['value'] ?? $default;
            }

            if (is_string($val) && in_array(strtolower($val), ['true', 'false'], true)) {
                return strtolower($val) === 'true';
            }

            return $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
