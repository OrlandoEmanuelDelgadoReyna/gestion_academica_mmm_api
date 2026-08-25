<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Material;
use App\Models\TipoMaterial;
use App\Support\MaterialStorage;
use Illuminate\Validation\Validator;

trait ValidatesMaterialResource
{
    protected function resolvedTipoCodigo(): ?string
    {
        $tipoId = $this->input('tipo_material_id');
        if (is_numeric($tipoId)) {
            $codigo = TipoMaterial::query()->whereKey((int) $tipoId)->value('codigo');

            return is_string($codigo) ? $codigo : null;
        }

        $material = $this->route('material');
        if ($material instanceof Material) {
            if (! $material->relationLoaded('tipoMaterial')) {
                $material->load('tipoMaterial');
            }

            return $material->tipoMaterial?->codigo;
        }

        return null;
    }

    /** @return list<string> */
    protected function archivoRules(): array
    {
        $rules = ['nullable', 'file', 'max:'.MaterialStorage::MAX_KILOBYTES, MaterialStorage::mimesRule()];
        $codigo = $this->resolvedTipoCodigo();

        if (in_array($codigo, ['VIDEO', 'ENLACE'], true)) {
            return ['prohibited'];
        }

        return $rules;
    }

    /** @return list<string> */
    protected function rutaRecursoRules(bool $requiredWhenUrlOnly): array
    {
        $codigo = $this->resolvedTipoCodigo();
        $urlRule = 'regex:/^https?:\\/\\//i';

        if (in_array($codigo, ['VIDEO', 'ENLACE'], true)) {
            $rules = ['string', 'max:2048', $urlRule];
            array_unshift($rules, $requiredWhenUrlOnly ? 'required' : 'sometimes');

            return $rules;
        }

        return ['nullable', 'string', 'max:2048'];
    }

    protected function afterValidatingMaterialResource(Validator $validator): void
    {
        $codigo = $this->resolvedTipoCodigo();
        $hasFile = $this->hasFile('archivo');
        $ruta = trim((string) $this->input('ruta_recurso', ''));
        $hasRuta = $ruta !== '';
        $isUpdate = $this->route('material') instanceof Material;

        if ($hasFile && $hasRuta) {
            $validator->errors()->add('archivo', 'Envíe un archivo o una URL, no ambos.');
            $validator->errors()->add('ruta_recurso', 'Envíe un archivo o una URL, no ambos.');
        }

        if ($hasFile) {
            $extension = strtolower((string) $this->file('archivo')?->getClientOriginalExtension());
            if ($extension === '' || ! in_array($extension, MaterialStorage::DOCUMENT_EXTENSIONS, true)) {
                $validator->errors()->add('archivo', 'El formato de archivo no está permitido.');
            }
        }

        if (in_array($codigo, ['VIDEO', 'ENLACE'], true)) {
            if ($hasFile) {
                $validator->errors()->add('archivo', 'Este tipo de material no admite archivos.');
            }
            if (! $isUpdate && ! $hasRuta) {
                $validator->errors()->add('ruta_recurso', 'Debe indicar una URL http o https.');
            }
            if ($hasRuta && ! MaterialStorage::isExternalUrl($ruta)) {
                $validator->errors()->add('ruta_recurso', 'La URL debe comenzar con http:// o https://.');
            }
            if ($isUpdate && $this->has('tipo_material_id') && ! $hasRuta && ! $hasFile) {
                $material = $this->route('material');
                if ($material instanceof Material && ! MaterialStorage::isExternalUrl($material->ruta_recurso)) {
                    $validator->errors()->add('ruta_recurso', 'Debe indicar una URL http o https.');
                }
            }
        }

        if ($codigo === 'DOCUMENTO') {
            if ($hasRuta && ! MaterialStorage::isExternalUrl($ruta)) {
                $validator->errors()->add('ruta_recurso', 'La URL debe comenzar con http:// o https://.');
            }
            if (! $isUpdate && ! $hasFile && ! $hasRuta) {
                $validator->errors()->add('ruta_recurso', 'Debe adjuntar un archivo o indicar una URL http o https.');
            }
        }
    }
}
