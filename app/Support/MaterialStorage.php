<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Private local-disk helpers for material files. */
final class MaterialStorage
{
    public const DISK = 'local';

    public const DIRECTORY = 'materiales';

    public const ENTREGA_DIRECTORY = 'entregas-tarea';

    public const CERTIFICADO_DIRECTORY = 'certificados';

    public const MAX_KILOBYTES = 10240;

    /** @var list<string> */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'txt', 'rtf', 'xls', 'xlsx', 'ppt', 'pptx'];

    /** @var list<string> */
    public const ENTREGA_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'odt',
        'txt',
        'rtf',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

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
            && (
                str_starts_with($normalized, self::DIRECTORY.'/')
                || str_starts_with($normalized, self::ENTREGA_DIRECTORY.'/')
                || str_starts_with($normalized, self::CERTIFICADO_DIRECTORY.'/')
            );
    }

    public static function storeUpload(UploadedFile $archivo, string $directory = self::DIRECTORY): string
    {
        $path = Storage::disk(self::DISK)->put($directory, $archivo);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException(
                $directory === self::DIRECTORY
                    ? 'No se pudo guardar el archivo del material.'
                    : 'No se pudo guardar el archivo de la entrega.',
            );
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

    public static function storeContents(string $contents, string $directory, string $extension = 'pdf'): string
    {
        $normalized = trim($directory, '/');
        if ($normalized === '') {
            throw new RuntimeException('Directorio de almacenamiento inválido.');
        }

        $path = $normalized.'/'.Str::uuid().'.'.ltrim($extension, '.');

        if (! Storage::disk(self::DISK)->put($path, $contents)) {
            throw new RuntimeException('No se pudo guardar el documento del certificado.');
        }

        return $path;
    }

    public static function mimesRule(): string
    {
        return 'mimes:'.implode(',', self::DOCUMENT_EXTENSIONS);
    }

    public static function entregaMimesRule(): string
    {
        return 'mimes:'.implode(',', self::ENTREGA_EXTENSIONS);
    }
}
