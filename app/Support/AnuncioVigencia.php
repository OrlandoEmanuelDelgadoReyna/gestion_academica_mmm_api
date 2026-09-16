<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Church-local announcement window. Naive client datetimes are America/Lima;
 * comparisons and storage use UTC instants so app timezone stays UTC.
 */
final class AnuncioVigencia
{
    public const TIMEZONE = 'America/Lima';

    public static function now(?CarbonInterface $at = null): Carbon
    {
        return ($at ?? now())->copy()->timezone(self::TIMEZONE);
    }

    public static function parsePublicadoAt(mixed $value): ?Carbon
    {
        return self::parseNaiveAsLima($value, endOfDayIfDateOnly: false);
    }

    public static function parseVenceAt(mixed $value): ?Carbon
    {
        return self::parseNaiveAsLima($value, endOfDayIfDateOnly: true);
    }

    public static function isOpen(?CarbonInterface $publicadoAt, ?CarbonInterface $venceAt, ?CarbonInterface $at = null): bool
    {
        $instant = ($at ?? now())->copy()->utc();

        if ($publicadoAt !== null && $publicadoAt->copy()->utc()->gt($instant)) {
            return false;
        }

        if ($venceAt !== null && $venceAt->copy()->utc()->lt($instant)) {
            return false;
        }

        return true;
    }

    public static function constrainVigente(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $instant = ($at ?? now())->copy()->utc();

        return $query
            ->where(function (Builder $builder) use ($instant): void {
                $builder->whereNull('publicado_at')->orWhere('publicado_at', '<=', $instant);
            })
            ->where(function (Builder $builder) use ($instant): void {
                $builder->whereNull('vence_at')->orWhere('vence_at', '>=', $instant);
            });
    }

    private static function parseNaiveAsLima(mixed $value, bool $endOfDayIfDateOnly): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->utc();
        }

        $raw = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $wall = $endOfDayIfDateOnly ? '23:59:59' : '00:00:00';

            return Carbon::parse($raw.' '.$wall, self::TIMEZONE)->utc();
        }

        if (self::isNaiveDatetime($raw)) {
            return Carbon::parse($raw, self::TIMEZONE)->utc();
        }

        return Carbon::parse($raw)->utc();
    }

    private static function isNaiveDatetime(string $raw): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $raw) === 1
            && preg_match('/Z|[+-]\d{2}:?\d{2}$/i', $raw) !== 1;
    }
}
