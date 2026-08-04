<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramacionAcademicaRequest;
use App\Http\Requests\TransitionProgramacionAcademicaRequest;
use App\Http\Requests\UpdateProgramacionAcademicaRequest;
use App\Http\Resources\ProgramacionAcademicaResource;
use App\Models\ProgramacionAcademica;
use App\Services\ProgramacionAcademicaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProgramacionAcademicaController extends Controller
{
    public function __construct(private ProgramacionAcademicaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ProgramacionAcademica::class);

        return ProgramacionAcademicaResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreProgramacionAcademicaRequest $request): ProgramacionAcademicaResource
    {
        return new ProgramacionAcademicaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(ProgramacionAcademica $programacionAcademica): ProgramacionAcademicaResource
    {
        $this->authorize('view', $programacionAcademica);

        return new ProgramacionAcademicaResource($programacionAcademica->load(['curso', 'aula', 'docentes', 'estadosMembresiaPermitidos']));
    }

    public function update(UpdateProgramacionAcademicaRequest $request, ProgramacionAcademica $programacionAcademica): ProgramacionAcademicaResource
    {
        return new ProgramacionAcademicaResource($this->service->update($programacionAcademica, $request->validated(), $request->user()->id));
    }

    public function transition(TransitionProgramacionAcademicaRequest $request, ProgramacionAcademica $programacionAcademica): ProgramacionAcademicaResource
    {
        return new ProgramacionAcademicaResource(
            $this->service->transitionEstado($programacionAcademica, (string) $request->string('estado'), $request->user()->id)
        );
    }
}
