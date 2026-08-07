<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPersonData;
use App\Support\Validation\PersonDataRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMiembroRequest extends FormRequest
{
    use NormalizesPersonData;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('miembro')) ?? false;
    }

    public function rules(): array
    {
        $miembro = $this->route('miembro');
        $miembroId = is_object($miembro) ? $miembro->id : $miembro;
        $iglesiaId = $this->filled('iglesia_id')
            ? $this->integer('iglesia_id')
            : (is_object($miembro) ? $miembro->iglesia_id : null);
        $tipoDocumento = $this->filled('tipo_documento')
            ? $this->string('tipo_documento')->toString()
            : (is_object($miembro) ? $miembro->tipo_documento : 'DNI');

        $dniRules = PersonDataRules::dni(required: false);
        $dniRules[] = Rule::unique('miembros', 'numero_documento')
            ->ignore($miembroId)
            ->where(
                fn ($query) => $query
                    ->where('iglesia_id', $iglesiaId)
                    ->where('tipo_documento', $tipoDocumento)
                    ->whereNull('deleted_at'),
            );

        return [
            'iglesia_id' => ['sometimes', 'integer', 'exists:iglesias,id'],
            'tipo_documento' => ['sometimes', 'string', Rule::in(['DNI'])],
            'numero_documento' => $dniRules,
            'nombres' => PersonDataRules::nombres(required: false),
            'apellidos' => PersonDataRules::apellidos(required: false),
            'fecha_nacimiento' => PersonDataRules::fechaNacimiento(),
            'sexo' => PersonDataRules::sexo(),
            'correo_electronico' => PersonDataRules::correo(),
            'telefono' => PersonDataRules::celular(),
            'direccion' => PersonDataRules::direccion(),
        ];
    }

    public function messages(): array
    {
        return PersonDataRules::messages();
    }
}
