<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntregaTareaRequest;
use App\Http\Requests\UpdateEntregaTareaRequest;
use App\Http\Resources\EntregaTareaResource;
use App\Models\EntregaTarea;
use App\Services\EntregaTareaService;

final class EntregaTareaController extends Controller
{
    public function __construct(private EntregaTareaService $service) {}

    public function store(StoreEntregaTareaRequest $request): EntregaTareaResource
    {
        return new EntregaTareaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(EntregaTarea $entregaTarea): EntregaTareaResource
    {
        $this->authorize('view', $entregaTarea);

        return new EntregaTareaResource($entregaTarea->load(['tarea', 'matricula']));
    }

    public function update(UpdateEntregaTareaRequest $request, EntregaTarea $entregaTarea): EntregaTareaResource
    {
        return new EntregaTareaResource($this->service->update($entregaTarea, $request->validated(), $request->user()->id));
    }
}
