<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Private bucket helpers for authenticated-user profile photographs. */
final class UsuarioProfilePhotoStorage
{
    public const DISK = ObjectStorage::DISK;

    public const DIRECTORY = 'users/profile';

    public const MAX_KILOBYTES = 5120;

    /** @var list<string> */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function isManagedPath(?string $ruta): bool
    {
        if ($ruta === null) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', trim($ruta)), '/');

        return $normalized !== ''
            && ! preg_match('#^https?://#i', $normalized)
            && str_starts_with($normalized, self::DIRECTORY.'/');
    }

    public static function storeUpload(UploadedFile $archivo): string
    {
        try {
            return ObjectStorage::storeUpload(self::DIRECTORY, $archivo);
        } catch (RuntimeException) {
            throw new RuntimeException('No se pudo guardar la fotografía de perfil.');
        }
    }

    public static function deleteManaged(?string $ruta): void
    {
        if (! self::isManagedPath($ruta)) {
            return;
        }

        ObjectStorage::delete(self::normalizedPath($ruta));
    }

    public static function url(?string $ruta): ?string
    {
        if (! self::isManagedPath($ruta)) {
            return null;
        }

        $url = ObjectStorage::temporaryUrl(self::normalizedPath($ruta));

        return $url !== '' ? $url : null;
    }

    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::EXTENSIONS);
    }

    private static function normalizedPath(string $ruta): string
    {
        return ltrim(str_replace('\\', '/', trim($ruta)), '/');
    }
}
