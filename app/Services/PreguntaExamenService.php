<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\OpcionPregunta;
use App\Models\PreguntaExamen;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Configures exam questions and options. Admin-only via policy. */
final class PreguntaExamenService
{
    public function __construct(
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    /** @return Collection<int, PreguntaExamen> */
    public function listar(ExamenFinal $examen, bool $soloActivas = false): Collection
    {
        $query = $examen->preguntas()->with('opciones')->orderBy('orden');
        if ($soloActivas) {
            $query->where('activo', true);
        }

        return $query->get();
    }

    public function create(ExamenFinal $examen, array $data, int $actor): PreguntaExamen
    {
        $this->assertOpciones($data['opciones'] ?? [], $data['tipo'] ?? 'seleccion_unica');

        return $this->transactions->execute(function () use ($examen, $data, $actor): PreguntaExamen {
            $orden = (int) ($data['orden'] ?? (($examen->preguntas()->max('orden') ?? 0) + 1));
            if ($examen->preguntas()->where('orden', $orden)->exists()) {
                throw ValidationException::withMessages(['orden' => 'Ya existe una pregunta con ese orden.']);
            }

            $pregunta = PreguntaExamen::query()->create([
                'examen_final_id' => $examen->id,
                'orden' => $orden,
                'tipo' => $data['tipo'] ?? 'seleccion_unica',
                'enunciado' => $data['enunciado'],
                'puntaje' => $data['puntaje'],
                'activo' => $data['activo'] ?? true,
            ]);
            $this->syncOpciones($pregunta, $data['opciones']);
            $this->auditorias->record($actor, 'CREATE', 'preguntas_examen', $pregunta->id, null, $pregunta->fresh()->getAttributes());

            return $pregunta->load('opciones');
        });
    }

    public function update(PreguntaExamen $pregunta, array $data, int $actor): PreguntaExamen
    {
        $tipo = $data['tipo'] ?? $pregunta->tipo;
        if (isset($data['opciones'])) {
            $this->assertOpciones($data['opciones'], $tipo);
        }

        return $this->transactions->execute(function () use ($pregunta, $data, $actor): PreguntaExamen {
            if (isset($data['orden']) && (int) $data['orden'] !== (int) $pregunta->orden) {
                $taken = PreguntaExamen::query()
                    ->where('examen_final_id', $pregunta->examen_final_id)
                    ->where('orden', $data['orden'])
                    ->where('id', '!=', $pregunta->id)
                    ->exists();
                if ($taken) {
                    throw ValidationException::withMessages(['orden' => 'Ya existe una pregunta con ese orden.']);
                }
            }

            $before = $pregunta->getAttributes();
            $pregunta->update(array_filter([
                'orden' => $data['orden'] ?? null,
                'tipo' => $data['tipo'] ?? null,
                'enunciado' => $data['enunciado'] ?? null,
                'puntaje' => $data['puntaje'] ?? null,
                'activo' => array_key_exists('activo', $data) ? $data['activo'] : null,
            ], fn ($value) => $value !== null));

            if (isset($data['opciones'])) {
                $this->assertSinRespuestas($pregunta);
                $pregunta->opciones()->delete();
                $this->syncOpciones($pregunta->fresh(), $data['opciones']);
            }

            $updated = $pregunta->fresh('opciones');
            $this->auditorias->record($actor, 'UPDATE', 'preguntas_examen', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(PreguntaExamen $pregunta, int $actor): void
    {
        $this->transactions->execute(function () use ($pregunta, $actor): void {
            $this->assertSinRespuestas($pregunta);
            $before = $pregunta->getAttributes();
            $pregunta->opciones()->delete();
            $pregunta->delete();
            $this->auditorias->record($actor, 'DELETE', 'preguntas_examen', $pregunta->id, $before, null);
        });
    }

    private function assertSinRespuestas(PreguntaExamen $pregunta): void
    {
        if ($pregunta->respuestas()->exists()) {
            throw ValidationException::withMessages([
                'pregunta' => 'No se puede modificar o eliminar una pregunta que ya tiene respuestas.',
            ]);
        }
    }

    /** @param list<array<string, mixed>> $opciones */
    private function syncOpciones(PreguntaExamen $pregunta, array $opciones): void
    {
        foreach ($opciones as $index => $opcion) {
            OpcionPregunta::query()->create([
                'pregunta_examen_id' => $pregunta->id,
                'orden' => $opcion['orden'] ?? ($index + 1),
                'texto' => $opcion['texto'],
                'es_correcta' => (bool) ($opcion['es_correcta'] ?? false),
            ]);
        }
    }

    /** @param list<array<string, mixed>> $opciones */
    private function assertOpciones(array $opciones, string $tipo): void
    {
        if ($opciones === []) {
            throw ValidationException::withMessages(['opciones' => 'Cada pregunta debe tener al menos una opción.']);
        }

        $correctas = collect($opciones)->filter(fn ($opcion) => (bool) ($opcion['es_correcta'] ?? false))->count();
        if ($correctas < 1) {
            throw ValidationException::withMessages(['opciones' => 'Debe haber al menos una opción correcta.']);
        }

        if ($tipo === 'seleccion_unica' && $correctas > 1) {
            throw ValidationException::withMessages(['opciones' => 'La selección única admite una sola opción correcta.']);
        }
    }
}
