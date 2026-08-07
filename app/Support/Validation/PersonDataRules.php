<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Rules\BirthDateWithinRange;
use App\Rules\PersonName;
use App\Rules\PeruvianCelular;
use App\Rules\PeruvianDni;
use Illuminate\Validation\Rule;

/**
 * Reusable person-data validation rule sets for FormRequests across modules
 * (Miembros, Usuarios-linked people, Docentes, Directivos, etc.).
 */
final class PersonDataRules
{
    /** @return list<mixed> */
    public static function dni(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'size:8',
            new PeruvianDni,
        ];
    }

    /** @return list<mixed> */
    public static function celular(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'size:9',
            new PeruvianCelular,
        ];
    }

    /** @return list<mixed> */
    public static function nombres(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            'max:120',
            new PersonName,
        ];
    }

    /** @return list<mixed> */
    public static function apellidos(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'string',
            'max:120',
            new PersonName,
        ];
    }

    /** @return list<mixed> */
    public static function sexo(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            Rule::in(['M', 'F']),
        ];
    }

    /** @return list<mixed> */
    public static function correo(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'email',
            'max:150',
        ];
    }

    /**
     * Soft address rules: length only so Jr., Av., Mz., Lt., N°, etc. are allowed.
     *
     * @return list<mixed>
     */
    public static function direccion(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:255',
        ];
    }

    /** @return list<mixed> */
    public static function fechaNacimiento(bool $required = false, int $maxAgeYears = 120): array
    {
        return [
            $required ? 'required' : 'nullable',
            'date',
            'before_or_equal:today',
            new BirthDateWithinRange($maxAgeYears),
        ];
    }

    /** Shared Spanish messages for person fields. */
    public static function messages(): array
    {
        return [
            'numero_documento.required' => 'El DNI es obligatorio.',
            'numero_documento.size' => 'El DNI debe tener exactamente 8 dígitos (ejemplo: 72719838).',
            'numero_documento.unique' => 'El número de documento ya está registrado.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.max' => 'Los nombres no pueden superar 120 caracteres.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.max' => 'Los apellidos no pueden superar 120 caracteres.',
            'sexo.in' => 'Seleccione Masculino o Femenino.',
            'sexo.required' => 'Seleccione el sexo (Masculino o Femenino).',
            'correo_electronico.required' => 'El correo electrónico es obligatorio.',
            'correo_electronico.email' => 'El correo no tiene un formato válido (ejemplo: ejemplo@correo.com).',
            'correo_electronico.max' => 'El correo no puede superar 150 caracteres.',
            'telefono.required' => 'El celular es obligatorio.',
            'telefono.size' => 'El celular debe tener exactamente 9 dígitos (ejemplo: 992932014).',
            'direccion.max' => 'La dirección no puede superar 255 caracteres.',
            'direccion.required' => 'La dirección es obligatoria.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser una fecha futura.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no es válida.',
        ];
    }
}
