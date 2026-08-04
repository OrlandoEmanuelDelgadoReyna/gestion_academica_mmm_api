<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBloqueCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('bloque_culto')) ?? false;
    }

    public function rules(): array
    {
        $bloque = $this->route('bloque_culto');
        $cultoId = (int) ($this->integer('culto_id') ?: $bloque->culto_id);

        return [
            'culto_id' => ['sometimes', 'integer', 'exists:cultos,id'],
            'tipo_participacion_id' => ['sometimes', 'integer', 'exists:tipos_participacion,id'],
            'orden' => ['sometimes', 'integer', 'min:1', Rule::unique('bloques_culto', 'orden')->where('culto_id', $cultoId)->ignore($bloque->id)],
            'contenido' => ['nullable', 'string', 'max:500'],
        ];
    }
}
