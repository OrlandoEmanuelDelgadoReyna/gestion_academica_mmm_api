<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Material;
use App\Models\ProgramacionAcademica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreMaterialRequest extends FormRequest
{
    use ValidatesMaterialResource;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $programacionId = $this->input('programacion_academica_id');
        if (! is_numeric($programacionId)) {
            return $user->can('create', Material::class);
        }

        $programacion = ProgramacionAcademica::query()->find((int) $programacionId);
        if ($programacion === null) {
            return $user->can('create', Material::class);
        }

        return $user->can('create', [Material::class, $programacion]);
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'tipo_material_id' => ['required', 'integer', Rule::exists('tipos_material', 'id')->where('activo', true)],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'archivo' => $this->archivoRules(),
            'ruta_recurso' => $this->rutaRecursoRules(true),
            'publicado_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->afterValidatingMaterialResource($v));
    }
}
