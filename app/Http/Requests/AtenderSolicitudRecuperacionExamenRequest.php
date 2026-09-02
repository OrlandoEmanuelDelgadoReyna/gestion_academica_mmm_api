<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SolicitudRecuperacionExamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AtenderSolicitudRecuperacionExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $solicitud = $this->route('solicitud_recuperacion_examen')
            ?? $this->route('solicitudRecuperacionExamen');

        return $solicitud instanceof SolicitudRecuperacionExamen
            && ($this->user()?->can('update', $solicitud) ?? false);
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in([
                SolicitudRecuperacionExamen::APROBADA,
                SolicitudRecuperacionExamen::RECHAZADA,
            ])],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
