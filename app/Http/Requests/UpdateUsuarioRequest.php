<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Usuario $usuario */
        $usuario = $this->route('usuario');

        return $this->user()?->can('update', $usuario) ?? false;
    }

    public function rules(): array
    {
        /** @var Usuario $usuario */
        $usuario = $this->route('usuario');

        return [
            'nombre_usuario' => [
                'required',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique('usuarios', 'nombre_usuario')->ignore($usuario->id),
            ],
            'activo' => ['required', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.min' => 'Debe asignarse al menos un rol.',
        ];
    }
}
