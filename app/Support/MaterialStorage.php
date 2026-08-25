<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Private local-disk helpers for material files. */
final class MaterialStorage
{
    public const DISK = 'local';

    public const DIRECTORY = 'materiales';

    public const MAX_KILOBYTES = 10240;

    /** @var list<string> */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'txt', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx'];

    public static function isExternalUrl(?string $ruta): bool
    {
        if ($ruta === null) {
            return false;
        }

        return (bool) preg_match('#^https?://#i', trim($ruta));
    }

    public static function isManagedPath(?string $ruta): bool
    {
        if ($ruta === null) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', trim($ruta)), '/');

        return $normalized !== ''
            && ! self::isExternalUrl($normalized)
            && str_starts_with($normalized, self::DIRECTORY.'/');
    }

    public static function storeUpload(UploadedFile $archivo): string
    {
        $path = Storage::disk(self::DISK)->put(self::DIRECTORY, $archivo);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo guardar el archivo del material.');
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

    public static function exists(?string $ruta): bool
    {
        return self::isManagedPath($ruta) && Storage::disk(self::DISK)->exists($ruta);
    }

    public static function sizeBytes(?string $ruta): ?int
    {
        if (! self::exists($ruta)) {
            return null;
        }

        return Storage::disk(self::DISK)->size($ruta);
    }

    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::DOCUMENT_EXTENSIONS);
    }
}
