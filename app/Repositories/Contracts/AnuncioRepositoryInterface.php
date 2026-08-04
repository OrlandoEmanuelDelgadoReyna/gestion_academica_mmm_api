<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Anuncio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnuncioRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    public function create(array $data): Anuncio;

    public function update(Anuncio $anuncio, array $data): Anuncio;

    public function delete(Anuncio $anuncio): void;
}
