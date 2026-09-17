<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Anuncio;
use App\Services\AcademicAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreAnuncioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Anuncio::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['required', 'string'],
            'estado' => ['required', 'string', Rule::in(Anuncio::ESTADOS)],
            'audiencia' => ['required', 'string', Rule::in(Anuncio::AUDIENCIAS)],
            'publicado_at' => ['nullable', 'date'],
            'vence_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('audiencia')) {
            $this->merge(['audiencia' => Anuncio::AUDIENCIA_TODOS]);
        }
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
                $validator->errors()->add('iglesia_id', 'El anuncio debe pertenecer a su iglesia.');
            }
        });
    }
}
