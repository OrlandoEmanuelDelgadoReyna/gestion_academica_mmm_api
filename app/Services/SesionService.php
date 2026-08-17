<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Leccion;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\SesionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for session management within academic programs. */
final class SesionService
{
    public function __construct(
        private SesionRepositoryInterface $sesiones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $programacionAcademicaId = null): LengthAwarePaginator
    {
        return $this->sesiones->paginate($perPage, $programacionAcademicaId);
    }

    public function create(array $data, int $actor): Sesion
    {
        $this->validateSchedule($data);
        $this->validateLecciones($data);

        return $this->transactions->execute(function () use ($data, $actor): Sesion {
            $sesion = $this->sesiones->create($data);
            $this->auditorias->record($actor, 'CREATE', 'sesiones', $sesion->id, null, $sesion->getAttributes());

            return $sesion;
        });
    }

    public function update(Sesion $sesion, array $data, int $actor): Sesion
    {
        $merged = array_merge($sesion->getAttributes(), $data);
        $this->validateSchedule($merged);
        $this->validateLecciones($merged);

        return $this->transactions->execute(function () use ($sesion, $data, $actor): Sesion {
            $before = $sesion->getAttributes();
            $updated = $this->sesiones->update($sesion, $data);
            $this->auditorias->record($actor, 'UPDATE', 'sesiones', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    private function validateSchedule(array $data): void
    {
        if (isset($data['inicio_at'], $data['fin_at']) && $data['fin_at'] <= $data['inicio_at']) {
            throw ValidationException::withMessages(['fin_at' => 'La fecha de fin debe ser posterior a la de inicio.']);
        }
    }

    private function validateLecciones(array $data): void
    {
        if (empty($data['leccion_ids']) || ! isset($data['programacion_academica_id'])) {
            return;
        }

        $cursoId = ProgramacionAcademica::query()
            ->whereKey($data['programacion_academica_id'])
            ->value('curso_id');

        if ($cursoId === null) {
            return;
        }

        $validCount = Leccion::query()
            ->where('curso_id', $cursoId)
            ->whereIn('id', $data['leccion_ids'])
            ->count();

        if ($validCount !== count($data['leccion_ids'])) {
            throw ValidationException::withMessages(['leccion_ids' => 'Una o más lecciones no pertenecen al curso de la programación.']);
        }
    }
}
