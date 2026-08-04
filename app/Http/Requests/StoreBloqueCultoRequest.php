<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BloqueCulto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBloqueCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BloqueCulto::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'culto_id' => ['required', 'integer', 'exists:cultos,id'],
            'tipo_participacion_id' => ['required', 'integer', 'exists:tipos_participacion,id'],
            'orden' => ['required', 'integer', 'min:1', Rule::unique('bloques_culto', 'orden')->where('culto_id', $this->integer('culto_id'))],
            'contenido' => ['nullable', 'string', 'max:500'],
        ];
    }
}
