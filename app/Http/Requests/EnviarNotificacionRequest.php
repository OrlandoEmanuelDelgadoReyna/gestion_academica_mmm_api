<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EnviarNotificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('enviar', $this->route('notificacion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'usuario_ids' => ['required', 'array', 'min:1'],
            'usuario_ids.*' => ['integer', 'exists:usuarios,id'],
        ];
    }
}
