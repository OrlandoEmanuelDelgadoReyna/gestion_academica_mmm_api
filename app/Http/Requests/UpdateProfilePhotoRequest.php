<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'foto' => [
                'required',
                'file',
                UsuarioProfilePhotoStorage::mimesRule(),
                'max:'.UsuarioProfilePhotoStorage::MAX_KILOBYTES,
            ],
        ];
    }
}
