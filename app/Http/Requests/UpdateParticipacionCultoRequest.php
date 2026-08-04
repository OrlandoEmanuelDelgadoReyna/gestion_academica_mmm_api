<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateParticipacionCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('participacion_culto')) ?? false;
    }

    public function rules(): array
    {
        $participacion = $this->route('participacion_culto');
        $bloqueId = (int) ($this->integer('bloque_culto_id') ?: $participacion->bloque_culto_id);

        return [
            'bloque_culto_id' => ['sometimes', 'integer', 'exists:bloques_culto,id'],
            'miembro_id' => ['sometimes', 'integer', 'exists:miembros,id', Rule::unique('participaciones_culto', 'miembro_id')->where('bloque_culto_id', $bloqueId)->ignore($participacion->id)],
            'estado' => ['sometimes', 'string', 'max:30'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
