<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Material::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'tipo_material_id' => ['required', 'integer', 'exists:tipos_material,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'archivo' => ['required_without:ruta_recurso', 'file', 'max:10240'],
            'ruta_recurso' => ['required_without:archivo', 'string', 'max:2048'],
            'publicado_at' => ['nullable', 'date'],
        ];
    }
}
