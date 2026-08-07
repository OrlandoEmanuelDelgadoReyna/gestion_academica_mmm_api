<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Peruvian mobile: exactly 9 digits starting with 9. */
final class PeruvianCelular implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('El celular solo puede contener números (ejemplo: 992932014).');

            return;
        }

        $digits = (string) $value;
        if (! ctype_digit($digits)) {
            $fail('El celular solo puede contener números (ejemplo: 992932014).');

            return;
        }

        if (! str_starts_with($digits, '9')) {
            $fail('El celular debe comenzar con 9 (ejemplo: 992932014).');

            return;
        }

        if (strlen($digits) !== 9) {
            $fail('El celular debe tener exactamente 9 dígitos (ejemplo: 992932014).');
        }
    }
}
