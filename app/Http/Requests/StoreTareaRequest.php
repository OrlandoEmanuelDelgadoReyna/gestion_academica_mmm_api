<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('create', Tarea::class) ?? false)) {
            return false;
        }

        $programacionId = (int) $this->input('programacion_academica_id');
        if ($programacionId < 1) {
            return true;
        }

        $programacion = ProgramacionAcademica::query()->find($programacionId);
        if ($programacion === null) {
            return true;
        }

        return $this->user()?->can('view', $programacion) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'publicado_at' => ['required', 'date'],
            'fecha_limite_at' => ['nullable', 'date', 'after:publicado_at'],
            'puntaje_maximo' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
