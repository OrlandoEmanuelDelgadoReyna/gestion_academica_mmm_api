<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Matricula;
use App\Models\ProgramacionAcademica;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('create', Matricula::class) ?? false)) {
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
            'miembro_id' => ['required', 'integer', 'exists:miembros,id'],
        ];
    }
}
