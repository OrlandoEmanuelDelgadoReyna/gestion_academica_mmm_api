<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTareaRequest;
use App\Http\Requests\UpdateTareaRequest;
use App\Http\Resources\TareaResource;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\TareaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TareaController extends Controller
{
    public function __construct(
        private TareaService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tarea::class);

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

        return TareaResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $programacionId,
            $this->academicAccess->teacherListMiembroId($user),
            $this->academicAccess->studentListMiembroId($user),
        ));
    }

    public function store(StoreTareaRequest $request): TareaResource
    {
        return new TareaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Tarea $tarea): TareaResource
    {
        $this->authorize('view', $tarea);

        return new TareaResource($tarea->load(['programacionAcademica', 'creadoPor']));
    }

    public function update(UpdateTareaRequest $request, Tarea $tarea): TareaResource
    {
        return new TareaResource($this->service->update($tarea, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Tarea $tarea): JsonResponse
    {
        $this->authorize('delete', $tarea);

        $this->service->delete($tarea, $request->user()->id);

        return response()->json(status: 204);
    }
}
