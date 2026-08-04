<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\MaterialService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaterialController extends Controller
{
    public function __construct(private MaterialService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        return MaterialResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
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
