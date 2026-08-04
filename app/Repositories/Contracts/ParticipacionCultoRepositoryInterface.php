<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Culto;
use App\Models\ParticipacionCulto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ParticipacionCultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $bloqueCultoId = null): LengthAwarePaginator;

    public function create(array $data): ParticipacionCulto;

    public function update(ParticipacionCulto $participacionCulto, array $data): ParticipacionCulto;

    public function delete(ParticipacionCulto $participacionCulto): void;

    public function hasScheduleConflict(int $miembroId, Culto $culto, ?int $excludeParticipacionId = null): bool;
}
