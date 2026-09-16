<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Anuncio;
use App\Repositories\Contracts\AnuncioRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Support\AnuncioVigencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class AnuncioService
{
    public function __construct(
        private AnuncioRepositoryInterface $anuncios,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private NotificacionService $notificaciones,
        private AcademicAccess $academicAccess,
    ) {}

    public function paginate(int $perPage, int $iglesiaId): LengthAwarePaginator
    {
        return $this->anuncios->paginate($perPage, $iglesiaId);
    }

    public function paginatePublicados(int $perPage, int $iglesiaId): LengthAwarePaginator
    {
        return $this->anuncios->paginatePublicados($perPage, $iglesiaId);
    }

    public function create(array $data, int $actorId): Anuncio
    {
        $data['creado_por_usuario_id'] = $actorId;
        $data = $this->normalizePublication($data);
        $this->assertPublicationWindow($data);

        return $this->transactions->execute(function () use ($data, $actorId): Anuncio {
            $anuncio = $this->anuncios->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'anuncios', $anuncio->id, null, $anuncio->getAttributes());

            if ($anuncio->isPublicado()) {
                $this->auditorias->record($actorId, 'PUBLISH', 'anuncios', $anuncio->id, null, $anuncio->getAttributes());
                $this->dispatchPublication($anuncio, $actorId);
            }

            return $anuncio->load(['iglesia', 'creadoPor']);
        });
    }

    public function update(Anuncio $anuncio, array $data, int $actorId): Anuncio
    {
        return $this->transactions->execute(function () use ($anuncio, $data, $actorId): Anuncio {
            $locked = Anuncio::query()->lockForUpdate()->findOrFail($anuncio->id);
            $wasPublished = $locked->isPublicado();
            $merged = $this->normalizePublication(array_merge($locked->only([
                'titulo',
                'contenido',
                'estado',
                'publicado_at',
                'vence_at',
            ]), $data));
            $this->assertPublicationWindow($merged);

            $payload = array_merge($data, $this->publicationTimestamps($merged, $wasPublished));
            foreach (['publicado_at', 'vence_at'] as $field) {
                if (array_key_exists($field, $merged)) {
                    $payload[$field] = $merged[$field];
                }
            }

            $before = $locked->getAttributes();
            $updated = $this->anuncios->update($locked, $payload);
            $this->auditorias->record($actorId, 'UPDATE', 'anuncios', $updated->id, $before, $updated->getAttributes());

            if (! $wasPublished && $updated->isPublicado()) {
                $this->auditorias->record($actorId, 'PUBLISH', 'anuncios', $updated->id, $before, $updated->getAttributes());
                $this->dispatchPublication($updated, $actorId);
            }

            return $updated->load(['iglesia', 'creadoPor']);
        });
    }

    public function delete(Anuncio $anuncio, int $actorId): void
    {
        $this->transactions->execute(function () use ($anuncio, $actorId): void {
            $before = $anuncio->getAttributes();
            $this->notificaciones->deleteGeneratedByAnuncio((int) $anuncio->id);
            $this->anuncios->delete($anuncio);
            $this->auditorias->record($actorId, 'DELETE', 'anuncios', $anuncio->id, $before, null);
        });
    }

    private function dispatchPublication(Anuncio $anuncio, int $actorId): void
    {
        $usuarioIds = $this->academicAccess->activeUsuarioIdsOfIglesia((int) $anuncio->iglesia_id);
        if ($usuarioIds === []) {
            return;
        }

        $this->notificaciones->dispatch([
            'iglesia_id' => $anuncio->iglesia_id,
            'titulo' => mb_substr((string) $anuncio->titulo, 0, 150),
            'contenido' => (string) $anuncio->contenido,
            'tipo' => 'anuncio',
            'anuncio_id' => $anuncio->id,
        ], $usuarioIds, $actorId);
    }

    /** @param  array<string, mixed>  $data */
    private function normalizePublication(array $data): array
    {
        if (($data['estado'] ?? null) === Anuncio::PUBLICADO && empty($data['publicado_at'])) {
            $data['publicado_at'] = now();
        }

        if (array_key_exists('publicado_at', $data)) {
            $data['publicado_at'] = AnuncioVigencia::parsePublicadoAt($data['publicado_at']);
        }

        if (array_key_exists('vence_at', $data)) {
            $data['vence_at'] = AnuncioVigencia::parseVenceAt($data['vence_at']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function publicationTimestamps(array $merged, bool $wasPublished): array
    {
        if ($wasPublished || ($merged['estado'] ?? null) !== Anuncio::PUBLICADO) {
            return [];
        }

        if (! empty($merged['publicado_at'])) {
            return ['publicado_at' => $merged['publicado_at']];
        }

        return ['publicado_at' => now()];
    }

    /** @param  array<string, mixed>  $data */
    private function assertPublicationWindow(array $data): void
    {
        $publicadoAt = $data['publicado_at'] ?? null;
        $venceAt = $data['vence_at'] ?? null;

        if ($venceAt !== null && $publicadoAt === null) {
            throw ValidationException::withMessages([
                'publicado_at' => 'La fecha de publicación es obligatoria cuando se define una fecha de vencimiento.',
            ]);
        }

        if ($publicadoAt !== null && $venceAt !== null && $venceAt < $publicadoAt) {
            throw ValidationException::withMessages([
                'vence_at' => 'La fecha de vencimiento debe ser posterior o igual a la fecha de publicación.',
            ]);
        }
    }
}
