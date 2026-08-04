<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Notificacion;
use Illuminate\Foundation\Http\FormRequest;

final class StoreNotificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Notificacion::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['required', 'string'],
            'tipo' => ['required', 'string', 'max:30'],
        ];
    }
}
