<?php

declare(strict_types=1);

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Birth date: not in the future and age at most 120 years. */
final class BirthDateWithinRange implements ValidationRule
{
    public function __construct(private readonly int $maxAgeYears = 120) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $fail('La fecha de nacimiento no es válida.');

            return;
        }

        $today = Carbon::today();
        if ($date->greaterThan($today)) {
            $fail('La fecha de nacimiento no puede ser futura.');

            return;
        }

        $minDate = $today->copy()->subYears($this->maxAgeYears);
        if ($date->lessThan($minDate)) {
            $fail("La edad no puede ser mayor a {$this->maxAgeYears} años.");
        }
    }
}
