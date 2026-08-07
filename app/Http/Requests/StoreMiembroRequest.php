<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPersonData;
use App\Models\Miembro;
use App\Support\Validation\PersonDataRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMiembroRequest extends FormRequest
{
    use NormalizesPersonData;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Miembro::class) ?? false;
    }

    public function rules(): array
    {
        $dniRules = PersonDataRules::dni();
        $dniRules[] = Rule::unique('miembros', 'numero_documento')->where(
            fn ($query) => $query
                ->where('iglesia_id', $this->integer('iglesia_id'))
                ->where('tipo_documento', $this->string('tipo_documento')->toString())
                ->whereNull('deleted_at'),
        );

        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'tipo_documento' => ['required', 'string', Rule::in(['DNI'])],
            'numero_documento' => $dniRules,
            'nombres' => PersonDataRules::nombres(),
            'apellidos' => PersonDataRules::apellidos(),
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
