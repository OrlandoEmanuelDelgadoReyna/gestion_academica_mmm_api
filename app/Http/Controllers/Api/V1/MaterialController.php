<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Models\ProgramacionAcademica;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaterialController extends Controller
{
    public function __construct(
        private MaterialService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

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

        return MaterialResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $programacionId,
            $this->academicAccess->listScopeMiembroId($user),
        ));
    }

    public function store(StoreMaterialRequest $request): MaterialResource
    {
        $data = $request->safe()->except('archivo');

        return new MaterialResource($this->service->create($data, $request->user()->id, $request->file('archivo')));
    }

    public function show(Material $material): MaterialResource
    {
        $this->authorize('view', $material);

        return new MaterialResource($material->load(['programacionAcademica.curso', 'tipoMaterial']));
    }

    public function update(UpdateMaterialRequest $request, Material $material): MaterialResource
    {
        $data = $request->safe()->except('archivo');

        return new MaterialResource($this->service->update($material, $data, $request->user()->id, $request->file('archivo')));
    }
}
