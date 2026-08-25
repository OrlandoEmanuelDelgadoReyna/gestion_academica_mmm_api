<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateMaterialRequest extends FormRequest
{
    use ValidatesMaterialResource;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('material')) ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_material_id' => ['sometimes', 'integer', Rule::exists('tipos_material', 'id')->where('activo', true)],
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'archivo' => $this->archivoRules(),
            'ruta_recurso' => $this->rutaRecursoRules(false),
            'publicado_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->afterValidatingMaterialResource($v));
    }
}
