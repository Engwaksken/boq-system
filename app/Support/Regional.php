<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Region defaults (currency, country, market location, timezone) come from the
 * admin Settings page, so the system is not tied to any one country. Values are
 * memoised per request.
 */
final class Regional
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function currency(): string
    {
        return self::remember('currency', fn () => strtoupper((string) (self::setting('currency') ?: config('app.default_currency', 'USD'))));
    }

    /** ISO 3166-1 alpha-2 code of the default country, or '' when not configured. */
    public static function countryCode(): string
    {
        return self::remember('country', fn () => strtoupper((string) (self::setting('country') ?: '')));
    }

    public static function countryName(): string
    {
        return self::remember('country_name', function () {
            $code = self::countryCode();

            if ($code === '' || ! self::hasTable('countries')) {
                return '';
            }

            return (string) (DB::table('countries')->where('iso2', $code)->value('name') ?? '');
        });
    }

    /** Default market/city used for price research, e.g. "Nairobi" or "Lagos". */
    public static function marketLocation(): string
    {
        return self::remember('market_location', fn () => trim((string) (self::setting('market_location') ?: '')));
    }

    public static function timezone(): string
    {
        return self::remember('timezone', function () {
            $timezone = (string) (self::setting('timezone') ?: config('app.timezone', 'UTC'));

            return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
        });
    }

    /** Clear memoised values (after settings change, and between tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    private static function remember(string $key, callable $resolve): string
    {
        return self::$cache[$key] ??= $resolve();
    }

    private static function setting(string $key): mixed
    {
        try {
            return self::hasTable('site_settings') ? SiteSetting::get($key) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
