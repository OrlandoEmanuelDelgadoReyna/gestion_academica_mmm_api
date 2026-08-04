<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('material')) ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_material_id' => ['sometimes', 'integer', 'exists:tipos_material,id'],
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'archivo' => ['sometimes', 'file', 'max:10240'],
            'ruta_recurso' => ['sometimes', 'string', 'max:2048'],
            'publicado_at' => ['nullable', 'date'],
        ];
    }
}
