<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EntregaTarea;
use App\Models\Matricula;
use App\Models\Tarea;
use App\Services\AcademicAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreEntregaTareaRequest extends FormRequest
{
    use ValidatesEntregaArchivo;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('create', EntregaTarea::class)) {
            return false;
        }

        $tareaId = $this->integer('tarea_id');
        if ($tareaId < 1) {
            return true;
        }

        $tarea = Tarea::query()->find($tareaId);
        if ($tarea === null) {
            return true;
        }

        if (! $user->can('view', $tarea)) {
            return false;
        }

        if (! app(AcademicAccess::class)->hasActiveEnrollment($user, (int) $tarea->programacion_academica_id)) {
            return false;
        }

        if (! $this->filled('matricula_id')) {
            return true;
        }

        $matricula = Matricula::query()->find($this->integer('matricula_id'));
        if ($matricula === null) {
            return true;
        }

        if ($user->miembro_id === null || (int) $matricula->miembro_id !== (int) $user->miembro_id) {
            return false;
        }

        if ((int) $matricula->programacion_academica_id !== (int) $tarea->programacion_academica_id) {
            return false;
        }

        return $matricula->estado === 'activa';
    }

    public function rules(): array
    {
        return [
            'tarea_id' => ['required', 'integer', 'exists:tareas,id'],
            'matricula_id' => ['sometimes', 'integer', 'exists:matriculas,id'],
            'contenido' => ['nullable', 'string'],
            'archivo' => $this->archivoRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->afterValidatingEntregaArchivo($v);

            if (blank($this->input('contenido')) && ! $this->hasFile('archivo')) {
                $v->errors()->add('contenido', 'Debe proporcionar contenido o un archivo.');
            }
        });
    }
}
