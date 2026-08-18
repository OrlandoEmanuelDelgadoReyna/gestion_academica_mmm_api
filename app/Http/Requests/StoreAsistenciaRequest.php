<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Asistencia;
use App\Models\Sesion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $sesion = Sesion::query()->find($this->integer('sesion_id'));

        return $user->can('create', [Asistencia::class, $sesion]);
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
