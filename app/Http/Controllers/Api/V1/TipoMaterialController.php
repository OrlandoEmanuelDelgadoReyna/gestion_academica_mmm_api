<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TipoMaterialResource;
use App\Models\Material;
use App\Models\TipoMaterial;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TipoMaterialController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        return TipoMaterialResource::collection(
            TipoMaterial::query()
                ->where('activo', true)
                ->orderBy('id')
                ->get(),
        );
    }
}
