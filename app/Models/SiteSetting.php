<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $setting->value,
            'float', 'double' => (float) $setting->value,
            'array', 'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function set(string $key, $value, string $group = 'general', string $type = 'string')
    {
        if (is_bool($value)) {
            $type = $type === 'string' ? 'boolean' : $type;
            $value = $value ? '1' : '0';
        } elseif (is_array($value) || is_object($value)) {
            $type = $type === 'string' ? 'json' : $type;
            $value = json_encode($value);
        } elseif (is_int($value)) {
            $type = $type === 'string' ? 'integer' : $type;
            $value = (string) $value;
        }

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
            ]
        );
    }
}
