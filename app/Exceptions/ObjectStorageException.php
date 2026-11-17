<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** Known failure while reading or writing the private media bucket. */
final class ObjectStorageException extends RuntimeException
{
    public const CODE = 'STORAGE_ERROR';

    public const STATUS = 503;

    public const WRITE_FAILED = 'No se pudo guardar el archivo.';

    public static function writeFailed(
        string $message = self::WRITE_FAILED,
        ?Throwable $previous = null,
    ): self {
        return new self($message, $previous);
    }

    public function __construct(
        string $message = self::WRITE_FAILED,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
