<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\IntentoExamen;
use Illuminate\Foundation\Http\FormRequest;

final class IniciarIntentoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', IntentoExamen::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'examen_final_id' => ['required', 'integer', 'exists:examenes_finales,id'],
            'matricula_id' => ['required', 'integer', 'exists:matriculas,id'],
        ];
    }
}
