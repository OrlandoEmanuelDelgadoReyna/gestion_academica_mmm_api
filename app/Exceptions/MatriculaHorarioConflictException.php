<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/** Validation failure for overlapping enrollee schedules (HTTP 422). */
final class MatriculaHorarioConflictException extends ValidationException
{
    public const CODE = 'HORARIO_CONFLICTO';

    public const USER_MESSAGE = 'No se puede realizar la matrícula. El miembro seleccionado ya está matriculado en una programación cuyo horario se cruza con la programación seleccionada.';

    /** @param array<string, mixed>|null $conflictoHorario */
    public static function make(?array $conflictoHorario = null): self
    {
        $validator = Validator::make([], []);
        $validator->errors()->add('miembro_id', self::USER_MESSAGE);

        $exception = new self($validator);
        $exception->conflictoHorario = $conflictoHorario;

        return $exception;
    }

    /** @var array<string, mixed>|null */
    public ?array $conflictoHorario = null;
}
