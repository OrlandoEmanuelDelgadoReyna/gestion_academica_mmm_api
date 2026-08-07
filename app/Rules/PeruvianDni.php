<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Peruvian DNI: exactly 8 numeric digits. */
final class PeruvianDni implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('El DNI solo puede contener números (ejemplo: 72719838).');

            return;
        }

        $digits = (string) $value;
        if (! ctype_digit($digits)) {
            $fail('El DNI solo puede contener números (ejemplo: 72719838).');

            return;
        }

        if (strlen($digits) !== 8) {
            $fail('El DNI debe tener exactamente 8 dígitos (ejemplo: 72719838).');
        }
    }
}
