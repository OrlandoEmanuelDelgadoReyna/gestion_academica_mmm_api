<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Asistencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asistencia::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sesion_id' => ['required', 'integer', 'exists:sesiones,id'],
            'matricula_id' => ['required', 'integer', 'exists:matriculas,id'],
            'estado' => ['required', 'string', Rule::in(['asistio', 'falto', 'justificado'])],
            'observacion' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->input('estado') === 'justificado')],
        ];
    }
}
