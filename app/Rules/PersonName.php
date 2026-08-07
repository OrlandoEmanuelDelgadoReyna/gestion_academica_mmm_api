<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Person given/family name: letters (incl. Spanish accents) and spaces only. */
final class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El campo solo puede contener letras y espacios.');

            return;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($normalized === '' || ! preg_match("/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u", $normalized)) {
            $fail('El campo solo puede contener letras y espacios (ejemplo: Orlando Emanuel).');
        }
    }
}
