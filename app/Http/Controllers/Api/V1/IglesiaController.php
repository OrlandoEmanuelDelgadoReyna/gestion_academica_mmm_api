<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIglesiaRequest;
use App\Http\Requests\UpdateIglesiaRequest;
use App\Http\Resources\IglesiaResource;
use App\Models\Iglesia;
use App\Services\IglesiaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IglesiaController extends Controller
{
    public function __construct(private IglesiaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Iglesia::class);

        return IglesiaResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreIglesiaRequest $request): IglesiaResource
    {
        return new IglesiaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Iglesia $iglesia): IglesiaResource
    {
        $this->authorize('view', $iglesia);

        return new IglesiaResource($iglesia->loadCount('miembros'));
    }

    public function update(UpdateIglesiaRequest $request, Iglesia $iglesia): IglesiaResource
    {
        return new IglesiaResource($this->service->update($iglesia, $request->validated(), $request->user()->id));
    }
}
