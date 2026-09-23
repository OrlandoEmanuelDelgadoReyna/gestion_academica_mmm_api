<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Certificado;
use App\Models\ProgramacionAcademica;
use App\Services\AcademicAccess;
use Illuminate\Foundation\Http\FormRequest;

final class EmitirCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->can('emitir', Certificado::class) ?? false)) {
            return false;
        }

        $programacionId = (int) $this->input('programacion_academica_id');
        if ($programacionId < 1) {
            return true;
        }

        $programacion = ProgramacionAcademica::query()->find($programacionId);
        if ($programacion === null) {
            return true;
        }

        return app(AcademicAccess::class)->teachesProgramacion($this->user(), $programacion);
    }

    public function rules(): array
    {
        return [
            'miembro_id' => ['required', 'integer', 'exists:miembros,id'],
            'tipo_certificado_id' => ['required', 'integer', 'exists:tipos_certificado,id'],
            'programacion_academica_id' => ['nullable', 'integer', 'exists:programaciones_academicas,id'],
            'destinatario' => ['nullable', 'string', 'max:150'],
            'vence_at' => ['nullable', 'date'],
        ];
    }
}
