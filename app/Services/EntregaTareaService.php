<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntregaTarea;
use App\Models\Matricula;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;
use Illuminate\Validation\ValidationException;

/** Handles task submissions and grading updates atomically. */
final class EntregaTareaService
{
    public function __construct(
        private EntregaTareaRepositoryInterface $entregas,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function create(array $data, int $actor): EntregaTarea
    {
        return $this->transactions->execute(function () use ($data, $actor): EntregaTarea {
            $this->assertMatriculaActiva((int) $data['matricula_id']);
            $this->assertContenidoOrArchivo($data);

            $payload = array_merge($data, ['entregado_at' => $data['entregado_at'] ?? now()]);
            $entrega = $this->entregas->create($payload);
            $this->auditorias->record($actor, 'CREATE', 'entregas_tarea', $entrega->id, null, $entrega->getAttributes());

            return $entrega;
        });
    }

    public function update(EntregaTarea $entrega, array $data, int $actor): EntregaTarea
    {
        return $this->transactions->execute(function () use ($entrega, $data, $actor): EntregaTarea {
            $this->assertMatriculaActiva($entrega->matricula_id);

            $merged = array_merge($entrega->only(['contenido', 'ruta_archivo']), $data);
            $this->assertContenidoOrArchivo($merged);

            $before = $entrega->getAttributes();
            $updated = $this->entregas->update($entrega, $data);
            $this->auditorias->record($actor, 'UPDATE', 'entregas_tarea', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    private function assertMatriculaActiva(int $matriculaId): void
    {
        if (! Matricula::query()->whereKey($matriculaId)->activa()->exists()) {
            throw ValidationException::withMessages(['matricula_id' => 'La matrícula debe estar activa.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertContenidoOrArchivo(array $data): void
    {
        if (blank($data['contenido'] ?? null) && blank($data['ruta_archivo'] ?? null)) {
            throw ValidationException::withMessages(['contenido' => 'Debe proporcionar contenido o un archivo.']);
        }
    }
}
