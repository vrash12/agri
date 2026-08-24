<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

final class LocalTime
{
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'Asia/Manila');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** @param DateTimeInterface|string|null $value */
    public static function fromUtc($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)->copy()
                : Carbon::parse((string) $value, (string) config('app.timezone', 'UTC'));

            return $date->setTimezone(self::timezone());
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** @param DateTimeInterface|string|null $value */
    public static function utcStartOfLocalDay($value = null): Carbon
    {
        return self::asLocalDate($value)->startOfDay()->utc();
    }

    /** @param DateTimeInterface|string|null $value */
    public static function utcEndOfLocalDay($value = null): Carbon
    {
        return self::asLocalDate($value)->endOfDay()->utc();
    }

    /** @param DateTimeInterface|string|null $value */
    private static function asLocalDate($value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->copy()->setTimezone(self::timezone());
        }

        return $value === null || $value === ''
            ? self::now()
            : Carbon::parse((string) $value, self::timezone());
    }
}
