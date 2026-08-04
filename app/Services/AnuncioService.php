<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Anuncio;
use App\Repositories\Contracts\AnuncioRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class AnuncioService
{
    public function __construct(
        private AnuncioRepositoryInterface $anuncios,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return $this->anuncios->paginate($perPage, $iglesiaId);
    }

    public function create(array $data, int $actorId): Anuncio
    {
        $data['creado_por_usuario_id'] = $actorId;
        $this->assertPublicationWindow($data);

        return $this->transactions->execute(function () use ($data, $actorId): Anuncio {
            $anuncio = $this->anuncios->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'anuncios', $anuncio->id, null, $anuncio->getAttributes());

            return $anuncio;
        });
    }

    public function update(Anuncio $anuncio, array $data, int $actorId): Anuncio
    {
        $this->assertPublicationWindow(array_merge($anuncio->only(['publicado_at', 'vence_at']), $data));

        return $this->transactions->execute(function () use ($anuncio, $data, $actorId): Anuncio {
            $before = $anuncio->getAttributes();
            $updated = $this->anuncios->update($anuncio, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'anuncios', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(Anuncio $anuncio, int $actorId): void
    {
        $this->transactions->execute(function () use ($anuncio, $actorId): void {
            $before = $anuncio->getAttributes();
            $this->anuncios->delete($anuncio);
            $this->auditorias->record($actorId, 'DELETE', 'anuncios', $anuncio->id, $before, null);
        });
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
