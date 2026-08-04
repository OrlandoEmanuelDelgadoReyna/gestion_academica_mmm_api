<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Evento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventoRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    public function create(array $data): Evento;

    public function update(Evento $evento, array $data): Evento;

    public function delete(Evento $evento): void;
}
