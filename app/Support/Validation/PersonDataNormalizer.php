<?php

declare(strict_types=1);

namespace App\Support\Validation;

/**
 * Shared person-data normalization for FormRequests across modules.
 */
final class PersonDataNormalizer
{
    public static function spaces(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    /** Title-case each word: "orlando emanuel" → "Orlando Emanuel". */
    public static function personName(?string $value): ?string
    {
        $normalized = self::spaces($value);
        if ($normalized === null) {
            return null;
        }

        return mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');
    }

    public static function email(?string $value): ?string
    {
        $normalized = self::spaces($value);
        if ($normalized === null) {
            return null;
        }

        return mb_strtolower($normalized, 'UTF-8');
    }

    public static function address(?string $value): ?string
    {
        return self::spaces($value);
    }

    public static function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Normalize known person fields present in the request payload.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $input): array
    {
        $map = [
            'nombres' => 'personName',
            'apellidos' => 'personName',
            'correo_electronico' => 'email',
            'direccion' => 'address',
            'telefono' => 'digits',
            'numero_documento' => 'digits',
        ];

        foreach ($map as $field => $method) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $raw = $input[$field];
            if ($raw === null) {
                continue;
            }

            $input[$field] = self::{$method}(is_string($raw) ? $raw : (string) $raw);
        }

        return $input;
    }
}
