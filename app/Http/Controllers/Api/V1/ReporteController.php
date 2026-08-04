<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReporteController extends Controller
{
    public function __construct(private ReporteService $service) {}

    public function academicos(Request $request): JsonResponse
    {
        $this->authorize('academicos', Reporte::class);

        return response()->json(['data' => $this->service->academicos()]);
    }

    public function administrativos(Request $request): JsonResponse
    {
        $this->authorize('administrativos', Reporte::class);

        return response()->json(['data' => $this->service->administrativos()]);
    }

    public function certificados(Request $request): JsonResponse
    {
        $this->authorize('certificados', Reporte::class);

        return response()->json(['data' => $this->service->certificados()]);
    }
}
