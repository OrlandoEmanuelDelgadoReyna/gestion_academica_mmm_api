<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamenFinal;
use Illuminate\Foundation\Http\FormRequest;

final class GenerarRecuperacionExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examen = $this->route('examenFinal') ?? $this->route('examen_final');

        return $examen instanceof ExamenFinal
            && ($this->user()?->can('generarRecuperacion', $examen) ?? false);
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'fecha' => ['required', 'date'],
        ];
    }
}
