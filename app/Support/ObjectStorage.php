<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
        $path = self::disk()->putFile($directory, $archivo, [
            'visibility' => 'private',
        ]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        return $path;
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

        return self::disk()->temporaryUrl($ruta, $expiration);
    }

    public static function ttlMinutes(): int
    {
        $minutes = (int) config('filesystems.disks.'.self::DISK.'.temporary_url_minutes', 60);

        return $minutes > 0 ? $minutes : 60;
    }
}
