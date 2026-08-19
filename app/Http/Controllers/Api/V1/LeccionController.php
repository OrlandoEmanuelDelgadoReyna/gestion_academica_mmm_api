<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeccionRequest;
use App\Http\Requests\UpdateLeccionRequest;
use App\Http\Resources\LeccionResource;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\LeccionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class LeccionController extends Controller
{
    public function __construct(
        private LeccionService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Leccion::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
        ]);

        $curso = Curso::query()->findOrFail((int) $validated['curso_id']);

        /** @var Usuario $user */
        $user = $request->user();
        abort_unless($this->academicAccess->teachesCursoId($user, $curso->id), 403);

        return LeccionResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $curso->id,
        ));
    }

    public function store(StoreLeccionRequest $request): LeccionResource
    {
        return new LeccionResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Leccion $leccion): LeccionResource
    {
        $this->authorize('view', $leccion);

        return new LeccionResource($leccion);
    }

    public function update(UpdateLeccionRequest $request, Leccion $leccion): LeccionResource
    {
        return new LeccionResource($this->service->update($leccion, $request->validated(), $request->user()->id));
    }
}
