<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMatriculaRequest;
use App\Http\Requests\TransitionMatriculaRequest;
use App\Http\Requests\UpdateMatriculaRequest;
use App\Http\Resources\MatriculaResource;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\MatriculaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class MatriculaController extends Controller
{
    public function __construct(
        private MatriculaService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Matricula::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'programacion_academica_id' => ['sometimes', 'integer', 'exists:programaciones_academicas,id'],
            'estado' => ['sometimes', 'string', Rule::in(['activa', 'retirada', 'completada'])],
        ]);

        $programacionId = isset($validated['programacion_academica_id'])
            ? (int) $validated['programacion_academica_id']
            : null;
        $estado = isset($validated['estado']) ? (string) $validated['estado'] : null;

        /** @var Usuario $user */
        $user = $request->user();

        if ($programacionId !== null) {
            $programacion = ProgramacionAcademica::query()->findOrFail($programacionId);
            $this->authorize('view', $programacion);
        }

        return MatriculaResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $programacionId,
            $estado,
            $this->academicAccess->listScopeMiembroId($user),
        ));
    }

    public function store(StoreMatriculaRequest $request): MatriculaResource
    {
        $programacion = ProgramacionAcademica::query()->findOrFail($request->integer('programacion_academica_id'));
        $miembro = Miembro::query()->findOrFail($request->integer('miembro_id'));

        return new MatriculaResource($this->service->create($programacion, $miembro, $request->user()->id));
    }

    public function show(Matricula $matricula): MatriculaResource
    {
        $this->authorize('view', $matricula);

        return new MatriculaResource($matricula->load([
            'programacionAcademica.curso',
            'programacionAcademica.horarios',
            'programacionAcademica.docentes',
            'miembro',
        ]));
    }

    public function update(UpdateMatriculaRequest $request, Matricula $matricula): MatriculaResource
    {
        return new MatriculaResource($this->service->update($matricula, $request->validated(), $request->user()->id));
    }

    public function transition(TransitionMatriculaRequest $request, Matricula $matricula): MatriculaResource
    {
        return new MatriculaResource(
            $this->service->updateEstado($matricula, (string) $request->string('estado'), $request->user()->id)
        );
    }
}
