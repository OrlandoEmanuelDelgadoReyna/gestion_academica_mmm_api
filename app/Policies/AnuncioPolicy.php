<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Anuncio;
use App\Models\Usuario;

final class AnuncioPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Anuncio $anuncio): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, Anuncio $anuncio): bool
    {
        return $this->allows($user);
    }

    public function delete(Usuario $user, Anuncio $anuncio): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
