<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Translation extends Model
{
    protected $fillable = ['locale', 'key_hash', 'key', 'value'];

    public static function cacheKey(string $locale): string
    {
        return 'ui-translations.'.$locale;
    }

    /**
     * Save (or clear, when the value is blank) one translation and refresh the cache.
     */
    public static function put(string $locale, string $key, ?string $value): void
    {
        $hash = sha1($key);
        $value = trim((string) $value);

        if ($value === '') {
            static::where('locale', $locale)->where('key_hash', $hash)->delete();
        } else {
            static::updateOrCreate(
                ['locale' => $locale, 'key_hash' => $hash],
                ['key' => $key, 'value' => $value],
            );
        }

        Cache::forget(static::cacheKey($locale));
    }

    /** @return array<string, string> */
    public static function linesFor(string $locale): array
    {
        return Cache::rememberForever(
            static::cacheKey($locale),
            fn () => static::where('locale', $locale)->pluck('value', 'key')->all(),
        );
    }
}
