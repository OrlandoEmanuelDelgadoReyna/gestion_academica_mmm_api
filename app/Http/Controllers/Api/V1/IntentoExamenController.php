<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnviarIntentoExamenRequest;
use App\Http\Requests\IniciarIntentoExamenRequest;
use App\Http\Resources\IntentoExamenResource;
use App\Models\IntentoExamen;
use App\Services\IntentoExamenService;

final class IntentoExamenController extends Controller
{
    public function __construct(private IntentoExamenService $service) {}

    public function iniciar(IniciarIntentoExamenRequest $request): IntentoExamenResource
    {
        $validated = $request->validated();

        return new IntentoExamenResource($this->service->iniciar(
            (int) $validated['examen_final_id'],
            (int) $validated['matricula_id'],
            $request->user()->id,
        ));
    }

    public function show(IntentoExamen $intentoExamen): IntentoExamenResource
    {
        $this->authorize('view', $intentoExamen);

        return new IntentoExamenResource($intentoExamen->load(['examenFinal', 'respuestas']));
    }

    public function enviar(EnviarIntentoExamenRequest $request, IntentoExamen $intentoExamen): IntentoExamenResource
    {
        return new IntentoExamenResource($this->service->enviar(
            $intentoExamen,
            $request->validated('respuestas'),
            $request->user()->id,
        ));
    }
}
