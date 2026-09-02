<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamenFinal;
use Illuminate\Foundation\Http\FormRequest;

final class StoreSolicitudRecuperacionExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examen = $this->route('examen_final') ?? $this->route('examenFinal');

        return $examen instanceof ExamenFinal
            && ($this->user()?->can('create', \App\Models\SolicitudRecuperacionExamen::class) ?? false)
            && ($this->user()?->can('view', $examen) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
