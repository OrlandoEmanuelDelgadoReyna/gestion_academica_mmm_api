<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ParticipacionCulto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreParticipacionCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ParticipacionCulto::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'bloque_culto_id' => ['required', 'integer', 'exists:bloques_culto,id'],
            'miembro_id' => ['required', 'integer', 'exists:miembros,id', Rule::unique('participaciones_culto', 'miembro_id')->where('bloque_culto_id', $this->integer('bloque_culto_id'))],
            'estado' => ['required', 'string', 'max:30'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
