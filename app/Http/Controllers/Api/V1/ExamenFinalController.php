<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AtenderSolicitudRecuperacionExamenRequest;
use App\Http\Requests\GenerarRecuperacionExamenRequest;
use App\Http\Requests\RegistrarNotaExamenRequest;
use App\Http\Requests\StoreExamenFinalRequest;
use App\Http\Requests\StorePreguntaExamenRequest;
use App\Http\Requests\StoreSolicitudRecuperacionExamenRequest;
use App\Http\Requests\UpdateExamenFinalRequest;
use App\Http\Requests\UpdatePreguntaExamenRequest;
use App\Http\Resources\ExamenFinalResource;
use App\Http\Resources\NotaExamenFinalResource;
use App\Http\Resources\PreguntaExamenResource;
use App\Http\Resources\SolicitudRecuperacionExamenResource;
use App\Models\ExamenFinal;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\PreguntaExamen;
use App\Models\ProgramacionAcademica;
use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\ExamenFinalService;
use App\Services\NotaExamenFinalService;
use App\Services\PreguntaExamenService;
use App\Services\SolicitudRecuperacionExamenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ExamenFinalController extends Controller
{
    public function __construct(
        private ExamenFinalService $service,
        private PreguntaExamenService $preguntas,
        private NotaExamenFinalService $notas,
        private SolicitudRecuperacionExamenService $solicitudes,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExamenFinal::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'programacion_academica_id' => ['sometimes', 'integer', 'exists:programaciones_academicas,id'],
        ]);

        $programacionId = isset($validated['programacion_academica_id'])
            ? (int) $validated['programacion_academica_id']
            : null;

        /** @var Usuario $user */
        $user = $request->user();

        if ($programacionId !== null) {
            $programacion = ProgramacionAcademica::query()->findOrFail($programacionId);
            $this->authorize('view', $programacion);
        }

        return ExamenFinalResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $programacionId,
            $this->academicAccess->teacherListMiembroId($user),
            $this->academicAccess->studentListMiembroId($user),
        ));
    }

    public function store(StoreExamenFinalRequest $request): ExamenFinalResource
    {
        return new ExamenFinalResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Request $request, ExamenFinal $examenFinal): ExamenFinalResource
    {
        $this->authorize('view', $examenFinal);

        return new ExamenFinalResource($examenFinal->load([
            'programacionAcademica',
            'creadoPor.miembro',
            'preguntas.opciones',
            'notas.matricula.miembro',
            'solicitudesRecuperacion.matricula.miembro',
        ]));
    }

    public function update(UpdateExamenFinalRequest $request, ExamenFinal $examenFinal): ExamenFinalResource
    {
        return new ExamenFinalResource($this->service->update($examenFinal, $request->validated(), $request->user()->id));
    }

    public function generarRecuperacion(
        GenerarRecuperacionExamenRequest $request,
        ExamenFinal $examenFinal,
    ): ExamenFinalResource {
        return new ExamenFinalResource(
            $this->service->generarRecuperacion($examenFinal, $request->validated(), $request->user()->id),
        );
    }

    public function destroy(Request $request, ExamenFinal $examenFinal): JsonResponse
    {
        $this->authorize('delete', $examenFinal);

        $this->service->delete($examenFinal, $request->user()->id);

        return response()->json(status: 204);
    }

    public function preguntas(Request $request, ExamenFinal $examenFinal): JsonResponse
    {
        $this->authorize('view', $examenFinal);
        $reveal = $request->user()?->can('manageQuestions', $examenFinal) ?? false;
        $items = $this->preguntas->listar($examenFinal, ! $reveal);

        return response()->json([
            'data' => $items->map(function (PreguntaExamen $pregunta) use ($reveal, $request) {
                $resource = new PreguntaExamenResource($pregunta);
                $resource->revealAnswers = $reveal;

                return $resource->resolve($request);
            })->values(),
        ]);
    }

    public function storePregunta(StorePreguntaExamenRequest $request, ExamenFinal $examenFinal): PreguntaExamenResource
    {
        $resource = new PreguntaExamenResource(
            $this->preguntas->create($examenFinal, $request->validated(), $request->user()->id),
        );
        $resource->revealAnswers = true;

        return $resource;
    }

    public function updatePregunta(UpdatePreguntaExamenRequest $request, PreguntaExamen $preguntaExamen): PreguntaExamenResource
    {
        $resource = new PreguntaExamenResource(
            $this->preguntas->update($preguntaExamen, $request->validated(), $request->user()->id),
        );
        $resource->revealAnswers = true;

        return $resource;
    }

    public function destroyPregunta(Request $request, PreguntaExamen $preguntaExamen): JsonResponse
    {
        $preguntaExamen->loadMissing('examenFinal');
        $this->authorize('manageQuestions', $preguntaExamen->examenFinal);

        $this->preguntas->delete($preguntaExamen, $request->user()->id);

        return response()->json(status: 204);
    }

    public function notas(Request $request, ExamenFinal $examenFinal): JsonResponse
    {
        $this->authorize('grade', $examenFinal);

        $rows = $this->notas->roster($examenFinal)->map(function (array $row) {
            /** @var Matricula $matricula */
            $matricula = $row['matricula'];
            $nota = $row['nota'];
            $solicitud = $row['solicitud'];
            $examen = $row['examen'];
            $minima = (float) $examen->nota_minima_aprobatoria;

            return [
                'matricula_id' => $matricula->id,
                'alumno' => ['nombre_completo' => $matricula->miembro?->nombre_completo],
                'estado_matricula' => $matricula->estado,
                'nota' => $nota instanceof NotaExamenFinal
                    ? (new NotaExamenFinalResource($nota->setRelation('examenFinal', $examen)))->resolve()
                    : null,
                'solicitud' => $solicitud instanceof SolicitudRecuperacionExamen
                    ? (new SolicitudRecuperacionExamenResource($solicitud))->resolve()
                    : null,
                'resultado' => $nota instanceof NotaExamenFinal ? $nota->resultado($minima) : null,
                'candidato_recuperacion' => $examen->esCandidatoRecuperacion($matricula, $nota instanceof NotaExamenFinal ? $nota : null),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    public function registrarNota(
        RegistrarNotaExamenRequest $request,
        ExamenFinal $examenFinal,
        Matricula $matricula,
    ): NotaExamenFinalResource {
        return new NotaExamenFinalResource(
            $this->notas->registrar($examenFinal, $matricula, $request->safe()->only(['nota']), $request->user()->id),
        );
    }

    public function solicitudes(Request $request, ExamenFinal $examenFinal): AnonymousResourceCollection
    {
        $this->authorize('grade', $examenFinal);

        return SolicitudRecuperacionExamenResource::collection($this->solicitudes->listarPorExamen($examenFinal));
    }

    public function solicitarRecuperacion(
        StoreSolicitudRecuperacionExamenRequest $request,
        ExamenFinal $examenFinal,
    ): SolicitudRecuperacionExamenResource {
        return new SolicitudRecuperacionExamenResource(
            $this->solicitudes->solicitar($examenFinal, $request->user()),
        );
    }

    public function atenderSolicitud(
        AtenderSolicitudRecuperacionExamenRequest $request,
        SolicitudRecuperacionExamen $solicitudRecuperacionExamen,
    ): SolicitudRecuperacionExamenResource {
        return new SolicitudRecuperacionExamenResource(
            $this->solicitudes->atender($solicitudRecuperacionExamen, $request->validated(), $request->user()->id),
        );
    }
}
