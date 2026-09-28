<?php

namespace App\Support;

use App\Models\Currency;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Formats numbers, money and dates using the signed-in user's display preferences
 * (Profile > Preferences), falling back to system defaults for guests.
 */
final class Format
{
    /** number_format pattern => [decimal separator, thousands separator] */
    private const SEPARATORS = [
        '1,234.56' => ['.', ','],
        '1.234,56' => [',', '.'],
        '1 234,56' => [',', ' '],
        "1'234.56" => ['.', "'"],
    ];

    /** @var array<string, int> */
    private static array $decimals = [];

    public static function number(float|int|string|null $value, int $decimals = 2): string
    {
        [$decimalSeparator, $thousandsSeparator] = self::separators();

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public static function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: Regional::currency());

        return $currency.' '.self::number($amount, self::currencyDecimals($currency));
    }

    /**
     * Short form for large figures in stat cards: 950, 12.5K, 2.6M, 1.3B.
     */
    public static function compact(float|int|string|null $value, int $decimals = 1): string
    {
        $value = (float) $value;
        $abs = abs($value);

        foreach ([1_000_000_000_000 => 'T', 1_000_000_000 => 'B', 1_000_000 => 'M', 1_000 => 'K'] as $size => $suffix) {
            if ($abs >= $size) {
                return self::number($value / $size, $decimals).$suffix;
            }
        }

        return self::number($value, 0);
    }

    /**
     * Null for empty values so callers can supply their own fallback (e.g. `?? 'Ongoing'`).
     */
    public static function date(DateTimeInterface|string|null $value, bool $withTime = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
        } catch (Throwable) {
            return (string) $value;
        }

        $user = auth()->user();

        if ($user?->timezone) {
            $date = $date->copy()->setTimezone($user->timezone);
        }

        $format = (string) ($user?->displayPreferences()['date_format'] ?? 'Y-m-d');

        return $date->format($withTime ? $format.' H:i' : $format);
    }

    /** @return array{0: string, 1: string} */
    private static function separators(): array
    {
        $pattern = (string) (auth()->user()?->displayPreferences()['number_format'] ?? '1,234.56');

        return self::SEPARATORS[$pattern] ?? self::SEPARATORS['1,234.56'];
    }

    private static function currencyDecimals(string $currency): int
    {
        if (! array_key_exists($currency, self::$decimals)) {
            try {
                self::$decimals[$currency] = (int) (Currency::where('code', $currency)->value('decimal_places') ?? 2);
            } catch (Throwable) {
                self::$decimals[$currency] = 2;
            }
        }

        return self::$decimals[$currency];
    }
}
