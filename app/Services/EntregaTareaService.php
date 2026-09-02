<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntregaTarea;
use App\Models\Matricula;
use App\Models\Tarea;
use App\Models\Usuario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;
use App\Support\MaterialStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Handles student task submissions and teacher grading of those submissions. */
final class EntregaTareaService
{
    public function __construct(
        private EntregaTareaRepositoryInterface $entregas,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private TareaNotificacionDispatcher $notificaciones,
    ) {}

    public function paginate(
        int $perPage,
        ?int $tareaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator {
        return $this->entregas->paginate($perPage, $tareaId, $assignedMiembroId, $enrolledMiembroId);
    }

    public function create(array $data, int $actorId, ?UploadedFile $archivo = null): EntregaTarea
    {
        $actor = Usuario::query()->findOrFail($actorId);
        $tarea = Tarea::query()->findOrFail((int) $data['tarea_id']);
        $matricula = $this->resolveOwnedActiveMatricula(
            $actor,
            $tarea,
            isset($data['matricula_id']) ? (int) $data['matricula_id'] : null,
        );

        $this->assertDeadline($tarea);
        $this->assertContenidoOrArchivo([
            'contenido' => $data['contenido'] ?? null,
            'ruta_archivo' => $archivo !== null ? 'pending' : null,
        ]);

        $storedPath = null;

        try {
            return $this->transactions->execute(function () use ($data, $actorId, $archivo, $tarea, $matricula, &$storedPath): EntregaTarea {
                $payload = [
                    'tarea_id' => $tarea->id,
                    'matricula_id' => $matricula->id,
                    'contenido' => $data['contenido'] ?? null,
                    'entregado_at' => now(),
                ];

                if ($archivo !== null) {
                    $storedPath = MaterialStorage::storeUpload($archivo, MaterialStorage::ENTREGA_DIRECTORY);
                    $payload = array_merge($payload, $this->archivoMetadata($archivo, $storedPath));
                }

                $entrega = $this->entregas->create($payload);
                $this->auditorias->record($actorId, 'CREATE', 'entregas_tarea', $entrega->id, null, $entrega->getAttributes());
                $loaded = $entrega->load(['tarea', 'matricula.miembro']);
                $this->notificaciones->entregaRealizada($loaded, $actorId);

                return $loaded;
            });
        } catch (UniqueConstraintViolationException) {
            MaterialStorage::deleteManaged($storedPath);
            throw $this->duplicateEntregaException();
        } catch (QueryException $exception) {
            MaterialStorage::deleteManaged($storedPath);
            if ($this->isUniqueConstraintFailure($exception)) {
                throw $this->duplicateEntregaException();
            }

            throw $exception;
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($storedPath);
            throw $exception;
        }
    }

    public function update(EntregaTarea $entrega, array $data, int $actorId, ?UploadedFile $archivo = null): EntregaTarea
    {
        $entrega->loadMissing(['tarea', 'matricula']);
        $this->assertDeadline($entrega->tarea);
        $this->assertNotCalificada($entrega);

        $eliminarArchivo = (bool) ($data['eliminar_archivo'] ?? false);
        unset($data['eliminar_archivo'], $data['nota'], $data['retroalimentacion'], $data['calificado_por_usuario_id'], $data['calificado_at'], $data['entregado_at'], $data['matricula_id'], $data['tarea_id'], $data['ruta_archivo']);

        $previousPath = $entrega->ruta_archivo;
        $storedPath = null;

        try {
            $updated = $this->transactions->execute(function () use ($entrega, $data, $actorId, $archivo, $eliminarArchivo, &$storedPath): EntregaTarea {
                if ($archivo !== null) {
                    $storedPath = MaterialStorage::storeUpload($archivo, MaterialStorage::ENTREGA_DIRECTORY);
                    $data = array_merge($data, $this->archivoMetadata($archivo, $storedPath));
                } elseif ($eliminarArchivo) {
                    $data['ruta_archivo'] = null;
                    $data['nombre_original'] = null;
                    $data['mime'] = null;
                    $data['tamano_bytes'] = null;
                }

                $merged = array_merge($entrega->only(['contenido', 'ruta_archivo']), $data);
                $this->assertContenidoOrArchivo($merged);

                $before = $entrega->getAttributes();
                $updated = $this->entregas->update($entrega, $data);
                $this->auditorias->record($actorId, 'UPDATE', 'entregas_tarea', $updated->id, $before, $updated->getAttributes());

                return $updated->load(['tarea', 'matricula.miembro']);
            });
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($storedPath);
            throw $exception;
        }

        if ($updated->ruta_archivo !== $previousPath) {
            MaterialStorage::deleteManaged($previousPath);
        }

        return $updated;
    }

    public function grade(EntregaTarea $entrega, array $data, int $actorId): EntregaTarea
    {
        $entrega->loadMissing('tarea');

        return $this->transactions->execute(function () use ($entrega, $data, $actorId): EntregaTarea {
            $before = $entrega->getAttributes();
            $updated = $this->entregas->update($entrega, [
                'nota' => $data['nota'],
                'retroalimentacion' => $data['retroalimentacion'] ?? null,
                'calificado_por_usuario_id' => $actorId,
                'calificado_at' => now(),
            ]);
            $this->auditorias->record(
                $actorId,
                'UPDATE',
                'entregas_tarea',
                $updated->id,
                $before,
                $updated->getAttributes(),
            );
            $loaded = $updated->load(['tarea', 'matricula.miembro', 'calificadoPor.miembro']);
            $this->notificaciones->entregaCalificada($loaded, $actorId);

            return $loaded;
        });
    }

    private function resolveOwnedActiveMatricula(Usuario $actor, Tarea $tarea, ?int $requestedMatriculaId): Matricula
    {
        if ($actor->miembro_id === null) {
            abort(403);
        }

        $matricula = Matricula::query()
            ->where('miembro_id', $actor->miembro_id)
            ->where('programacion_academica_id', $tarea->programacion_academica_id)
            ->where('estado', 'activa')
            ->first();

        if ($matricula === null) {
            abort(403);
        }

        if ($requestedMatriculaId !== null && $requestedMatriculaId !== (int) $matricula->id) {
            abort(403);
        }

        return $matricula;
    }

    private function assertDeadline(?Tarea $tarea): void
    {
        if ($tarea === null || $tarea->fecha_limite_at === null) {
            return;
        }

        if (now()->gt($tarea->fecha_limite_at)) {
            throw ValidationException::withMessages([
                'fecha_limite_at' => 'El plazo de entrega de esta tarea ya terminó.',
            ]);
        }
    }

    private function assertNotCalificada(EntregaTarea $entrega): void
    {
        if ($entrega->isCalificada()) {
            throw ValidationException::withMessages([
                'contenido' => 'Esta entrega ya fue calificada y no puede modificarse.',
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertContenidoOrArchivo(array $data): void
    {
        if (blank($data['contenido'] ?? null) && blank($data['ruta_archivo'] ?? null)) {
            throw ValidationException::withMessages(['contenido' => 'Debe proporcionar contenido o un archivo.']);
        }
    }

    /** @return array{ruta_archivo: string, nombre_original: string, mime: string|null, tamano_bytes: int|null} */
    private function archivoMetadata(UploadedFile $archivo, string $path): array
    {
        $original = $archivo->getClientOriginalName();

        return [
            'ruta_archivo' => $path,
            'nombre_original' => mb_substr($original, 0, 255),
            'mime' => $archivo->getClientMimeType() ?: $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
        ];
    }

    private function duplicateEntregaException(): ValidationException
    {
        return ValidationException::withMessages([
            'tarea_id' => 'Ya existe una entrega para esta tarea.',
        ]);
    }

    private function isUniqueConstraintFailure(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return $sqlState === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
