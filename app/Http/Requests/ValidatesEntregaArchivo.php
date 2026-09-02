<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MaterialStorage;
use Illuminate\Validation\Validator;

trait ValidatesEntregaArchivo
{
    /** @return list<string> */
    protected function archivoRules(): array
    {
        return [
            'nullable',
            'file',
            'max:'.MaterialStorage::MAX_KILOBYTES,
            MaterialStorage::entregaMimesRule(),
        ];
    }

    protected function afterValidatingEntregaArchivo(Validator $validator): void
    {
        if (! $this->hasFile('archivo')) {
            return;
        }

        $extension = strtolower((string) $this->file('archivo')?->getClientOriginalExtension());
        if ($extension === '' || ! in_array($extension, MaterialStorage::ENTREGA_EXTENSIONS, true)) {
            $validator->errors()->add('archivo', 'El formato de archivo no está permitido.');
        }
    }
}
