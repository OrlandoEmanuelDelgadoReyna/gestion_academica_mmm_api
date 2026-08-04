<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Curso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CursoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Curso;

    public function update(Curso $curso, array $data): Curso;
}
