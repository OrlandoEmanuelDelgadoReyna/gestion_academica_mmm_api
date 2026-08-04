<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ExamenFinal;
use App\Repositories\Contracts\ExamenFinalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentExamenFinalRepository implements ExamenFinalRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return ExamenFinal::query()->with('programacionAcademica')->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(array $data): ExamenFinal
    {
        return ExamenFinal::query()->create($data);
    }

    public function update(ExamenFinal $examen, array $data): ExamenFinal
    {
        $examen->update($data);

        return $examen->refresh();
    }

    public function delete(ExamenFinal $examen): void
    {
        $examen->delete();
    }
}
