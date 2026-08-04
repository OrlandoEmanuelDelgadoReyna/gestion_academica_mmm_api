<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;

final class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Usuario::class) ?? false;
    }

    public function rules(): array
    {
        return ['miembro_id' => ['required', 'integer', 'exists:miembros,id', 'unique:usuarios,miembro_id'], 'nombre_usuario' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:usuarios,nombre_usuario'], 'contrasena' => ['required', 'string', 'min:12', 'confirmed'], 'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['integer', 'distinct', 'exists:roles,id']];
    }

    public function messages(): array
    {
        return ['miembro_id.unique' => 'El miembro ya posee una cuenta de usuario.', 'roles.min' => 'Debe asignarse al menos un rol.'];
    }
}
