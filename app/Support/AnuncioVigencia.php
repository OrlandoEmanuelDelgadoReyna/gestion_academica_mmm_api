<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Unique announcement date contract:
 * - Storage and SQL comparisons use UTC instants.
 * - Naive client values are America/Lima wall time.
 * - vence_at is an inclusive Lima calendar day stored as 23:59:59 Lima → UTC.
 * - Naive/stored 00:00:00 is that calendar date, not an expiry at midnight.
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
        return self::parseIncoming($value, asEndOfLimaDay: false);
    }

    public static function parseVenceAt(mixed $value): ?Carbon
    {
        return self::parseIncoming($value, asEndOfLimaDay: true);
    }

    /** UTC instant used to decide whether vence_at has already passed. */
    public static function effectiveVenceAt(?CarbonInterface $venceAt): ?Carbon
    {
        if ($venceAt === null) {
            return null;
        }

        $utc = Carbon::instance($venceAt)->utc();

        if (self::isMidnight($utc)) {
            return Carbon::parse($utc->toDateString().' 23:59:59', self::TIMEZONE)->utc();
        }

        return $utc;
    }

    public static function limaDateString(?CarbonInterface $venceAt): ?string
    {
        $effective = self::effectiveVenceAt($venceAt);

        return $effective?->copy()->timezone(self::TIMEZONE)->toDateString();
    }

    /** First America/Lima midnight after the inclusive vence calendar day. */
    public static function purgeAt(?CarbonInterface $venceAt): ?Carbon
    {
        $limaDate = self::limaDateString($venceAt);
        if ($limaDate === null) {
            return null;
        }

        return Carbon::parse($limaDate, self::TIMEZONE)->startOfDay()->addDay();
    }

    public static function isOpen(?CarbonInterface $publicadoAt, ?CarbonInterface $venceAt, ?CarbonInterface $at = null): bool
    {
        $instant = ($at ?? now())->copy()->utc();

        if ($publicadoAt !== null && Carbon::instance($publicadoAt)->utc()->gt($instant)) {
            return false;
        }

        $effectiveVence = self::effectiveVenceAt($venceAt);
        if ($effectiveVence !== null && $effectiveVence->lt($instant)) {
            return false;
        }

        return true;
    }

    public static function constrainVigente(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $instant = ($at ?? now())->copy()->utc();
        $limaTodayStartUtc = Carbon::parse(self::now($at)->toDateString().' 00:00:00', 'UTC');

        return $query
            ->where(function (Builder $builder) use ($instant): void {
                $builder->whereNull('publicado_at')->orWhere('publicado_at', '<=', $instant);
            })
            ->where(function (Builder $builder) use ($instant, $limaTodayStartUtc): void {
                $builder
                    ->whereNull('vence_at')
                    ->orWhere('vence_at', '>=', $instant)
                    ->orWhere(function (Builder $legacy) use ($limaTodayStartUtc): void {
                        $legacy
                            ->where('vence_at', '>=', $limaTodayStartUtc)
                            ->whereTime('vence_at', '=', '00:00:00');
                    });
            });
    }

    private static function parseIncoming(mixed $value, bool $asEndOfLimaDay): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $asEndOfLimaDay
                ? self::effectiveVenceAt($value)
                : Carbon::instance($value)->utc();
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $wall = $asEndOfLimaDay ? '23:59:59' : '00:00:00';

            return Carbon::parse($raw.' '.$wall, self::TIMEZONE)->utc();
        }

        if (self::isNaiveDatetime($raw)) {
            if ($asEndOfLimaDay && self::isNaiveMidnight($raw)) {
                return Carbon::parse(substr($raw, 0, 10).' 23:59:59', self::TIMEZONE)->utc();
            }

            return Carbon::parse($raw, self::TIMEZONE)->utc();
        }

        $parsed = Carbon::parse($raw)->utc();

        return $asEndOfLimaDay ? self::effectiveVenceAt($parsed) : $parsed;
    }

    private static function isNaiveDatetime(string $raw): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $raw) === 1
            && preg_match('/Z|[+-]\d{2}:?\d{2}$/i', $raw) !== 1;
    }

    private static function isNaiveMidnight(string $raw): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[ T]00:00(:00)?$/', $raw) === 1;
    }

    private static function isMidnight(Carbon $utc): bool
    {
        return $utc->format('H:i:s') === '00:00:00';
    }
}
