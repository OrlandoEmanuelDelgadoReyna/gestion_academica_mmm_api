<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Certificado;
use App\Services\AcademicAccess;
use Illuminate\Foundation\Http\FormRequest;

final class ConsultarElegibilidadCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('consultarElegibilidad', Certificado::class)) {
            return false;
        }

        $miembroId = $this->integer('miembro_id');
        $programacionId = $this->integer('programacion_academica_id');
        if ($miembroId <= 0 || $programacionId <= 0) {
            return true;
        }

        return app(AcademicAccess::class)->canConsultarElegibilidad($user, $miembroId, $programacionId);
    }

    public function rules(): array
    {
        return [
            'miembro_id' => ['required', 'integer', 'exists:miembros,id'],
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'tipo_certificado_id' => ['nullable', 'integer', 'exists:tipos_certificado,id'],
        ];
    }
}
