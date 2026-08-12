<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Validation\ProgramacionHorarioRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProgramacionAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('programacionAcademica')) ?? false;
    }

    public function rules(): array
    {
        return [
            'aula_id' => ['sometimes', 'nullable', 'integer', 'exists:aulas,id'],
            'periodo' => ['sometimes', 'string', 'max:50'],
            'grupo' => ['sometimes', 'string', 'max:60'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'date'],
            'capacidad' => ['sometimes', 'integer', 'min:1'],
            'escala_maxima' => ['sometimes', 'numeric', 'min:0'],
            'nota_minima_aprobatoria' => ['sometimes', 'numeric', 'min:0'],
            'maximo_intentos_examen' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', 'string', Rule::in(['borrador', 'abierta', 'en_curso', 'cerrada', 'cancelada'])],
            'docente_ids' => ['sometimes', 'array'],
            'docente_ids.*' => ['integer', 'exists:miembros,id'],
            'estados_membresia_permitidos' => ['sometimes', 'array'],
            'estados_membresia_permitidos.*' => ['integer', 'exists:estados_membresia,id'],
            ...ProgramacionHorarioRules::updateRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        ProgramacionHorarioRules::after($validator);
    }
}
