<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Business failure when checking in attendance from a session QR. */
final class AsistenciaQrException extends RuntimeException
{
    public const QR_INVALIDO = 'QR_INVALIDO';

    public const SESION_CANCELADA = 'SESION_CANCELADA';

    public const SESION_NO_DISPONIBLE = 'SESION_NO_DISPONIBLE';

    public const SIN_MATRICULA = 'SIN_MATRICULA';

    public const ASISTENCIA_YA_REGISTRADA = 'ASISTENCIA_YA_REGISTRADA';

    public const ASISTENCIA_REGISTRADA = 'ASISTENCIA_REGISTRADA';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self(self::QR_INVALIDO, 'El código QR no es válido.');
    }

    public static function cancelled(): self
    {
        return new self(self::SESION_CANCELADA, 'Esta sesión está cancelada y no permite registrar asistencia.');
    }

    public static function unavailable(): self
    {
        return new self(self::SESION_NO_DISPONIBLE, 'Esta sesión no está disponible para registrar asistencia.');
    }

    public static function withoutEnrollment(): self
    {
        return new self(self::SIN_MATRICULA, 'No tienes una matrícula activa para esta sesión.');
    }

    public static function alreadyRegistered(): self
    {
        return new self(self::ASISTENCIA_YA_REGISTRADA, 'Tu asistencia para esta sesión ya fue registrada.');
    }
}
