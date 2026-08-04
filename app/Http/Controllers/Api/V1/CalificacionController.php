<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CalificacionResource;
use App\Models\Calificacion;
use App\Models\Matricula;
use App\Services\CalificacionService;

final class CalificacionController extends Controller
{
    public function __construct(private CalificacionService $service) {}

    public function calcular(Matricula $matricula): CalificacionResource
    {
        $this->authorize('calcular', Calificacion::class);

        return new CalificacionResource($this->service->calcular($matricula, request()->user()->id));
    }
}
