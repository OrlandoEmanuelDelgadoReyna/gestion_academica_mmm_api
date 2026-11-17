<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ObjectStorageException;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Private Railway/S3 bucket for catalog covers and profile photographs.
 *
 * FILESYSTEM_DISK stays local. Only this dedicated media disk is used here.
 */
final class ObjectStorage
{
    public const DISK = 'media';

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public static function storeUpload(string $directory, UploadedFile $archivo): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');

        try {
            // Do not pass visibility: Flysystem would send an object ACL that Tigris rejects.
            $path = self::disk()->putFile($directory, $archivo);
        } catch (Throwable $exception) {
            self::logFailure('write', $directory, $exception);

            throw ObjectStorageException::writeFailed(previous: $exception);
        }

        if (! is_string($path) || $path === '') {
            self::logFailure('write', $directory);

            throw ObjectStorageException::writeFailed();
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if ($directory !== '' && ! str_starts_with($normalized, $directory.'/')) {
            self::logFailure('write', $directory);

            throw ObjectStorageException::writeFailed();
        }

        return $normalized;
    }

    public static function delete(string $ruta): void
    {
        self::disk()->delete($ruta);
    }

    public static function exists(string $ruta): bool
    {
        return self::disk()->exists($ruta);
    }

    public static function temporaryUrl(string $ruta, ?DateTimeInterface $expiresAt = null): string
    {
        $expiration = $expiresAt ?? now()->addMinutes(self::ttlMinutes());

        try {
            $url = self::disk()->temporaryUrl($ruta, $expiration);
        } catch (Throwable $exception) {
            self::logFailure('temporary_url', $ruta, $exception);

            return '';
        }

        return is_string($url) ? $url : '';
    }

    public static function ttlMinutes(): int
    {
        $minutes = (int) config('filesystems.disks.'.self::DISK.'.temporary_url_minutes', 60);

        return $minutes > 0 ? $minutes : 60;
    }

    private static function logFailure(string $operation, string $target, ?Throwable $exception = null): void
    {
        Log::error('ObjectStorage operation failed.', [
            'operation' => $operation,
            'target' => $target,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => self::safeErrorMessage($exception),
        ]);
    }

    private static function safeErrorMessage(?Throwable $exception): string
    {
        if ($exception === null) {
            return 'empty_or_invalid_path';
        }

        $message = $exception->getMessage();
        $message = preg_replace('/AKIA[A-Z0-9]{16}/', '[redacted]', $message) ?? $message;
        $message = preg_replace(
            '/(?i)(secret|signature|credential|token|password)=([^&\s]+)/',
            '$1=[redacted]',
            $message,
        ) ?? $message;

        return $message;
    }
}
