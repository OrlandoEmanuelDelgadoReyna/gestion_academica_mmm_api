<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ProgramacionAcademica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProgramacionAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProgramacionAcademica::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'aula_id' => ['nullable', 'integer', 'exists:aulas,id'],
            'periodo' => ['required', 'string', 'max:50'],
            'grupo' => ['required', 'string', 'max:60'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date'],
            'capacidad' => ['required', 'integer', 'min:1'],
            'escala_maxima' => ['required', 'numeric', 'min:0'],
            'nota_minima_aprobatoria' => ['required', 'numeric', 'min:0'],
            'maximo_intentos_examen' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', 'string', Rule::in(['borrador', 'abierta', 'en_curso', 'cerrada', 'cancelada'])],
            'docente_ids' => ['sometimes', 'array'],
            'docente_ids.*' => ['integer', 'exists:miembros,id'],
            'estados_membresia_permitidos' => ['sometimes', 'array'],
            'estados_membresia_permitidos.*' => ['integer', 'exists:estados_membresia,id'],
        ];
    }
}
