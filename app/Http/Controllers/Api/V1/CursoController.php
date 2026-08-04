<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Resources\CursoResource;
use App\Models\Curso;
use App\Services\CursoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CursoController extends Controller
{
    public function __construct(private CursoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Curso::class);

        return CursoResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreCursoRequest $request): CursoResource
    {
        return new CursoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Curso $curso): CursoResource
    {
        $this->authorize('view', $curso);

        return new CursoResource($curso->load('iglesia'));
    }

    public function update(UpdateCursoRequest $request, Curso $curso): CursoResource
    {
        return new CursoResource($this->service->update($curso, $request->validated(), $request->user()->id));
    }
}
