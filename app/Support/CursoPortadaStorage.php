<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Public-disk helpers for course catalog covers. */
final class CursoPortadaStorage
{
    public const DISK = 'public';

    public const DIRECTORY = 'cursos/portadas';

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
        $path = Storage::disk(self::DISK)->putFile(self::DIRECTORY, $archivo);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo guardar la portada del curso.');
        }

        return $path;
    }

    public static function deleteManaged(?string $ruta): void
    {
        if (! self::isManagedPath($ruta)) {
            return;
        }

        Storage::disk(self::DISK)->delete($ruta);
    }

    public static function url(?string $ruta): ?string
    {
        if (! self::isManagedPath($ruta)) {
            return null;
        }

        $url = Storage::disk(self::DISK)->url($ruta);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::EXTENSIONS);
    }
}
