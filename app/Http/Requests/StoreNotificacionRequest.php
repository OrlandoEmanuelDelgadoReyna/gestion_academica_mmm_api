<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Notificacion;
use App\Services\AcademicAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if ($user === null) {
                return;
            }

            $iglesiaId = app(AcademicAccess::class)->iglesiaId($user);
            if ($iglesiaId !== null && $this->integer('iglesia_id') !== $iglesiaId) {
                $validator->errors()->add('iglesia_id', 'La notificación debe pertenecer a su iglesia.');
            }
        });
    }
}
