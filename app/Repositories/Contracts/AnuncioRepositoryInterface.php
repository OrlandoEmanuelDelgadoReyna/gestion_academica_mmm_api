<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Anuncio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnuncioRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    /** @param  list<string>|null  $audiencias */
    public function paginatePublicados(int $perPage, int $iglesiaId, ?array $audiencias = null): LengthAwarePaginator;

    public function create(array $data): Anuncio;

    public function update(Anuncio $anuncio, array $data): Anuncio;

    public function delete(Anuncio $anuncio): void;
}
