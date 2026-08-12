<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProgramacionHorario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProgramacionHorario */
final class ProgramacionHorarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dia_semana' => $this->dia_semana,
            'hora_inicio' => $this->formatTime($this->hora_inicio),
            'hora_fin' => $this->formatTime($this->hora_fin),
        ];
    }

    private function formatTime(mixed $value): string
    {
        $raw = (string) $value;

        return strlen($raw) >= 5 ? substr($raw, 0, 5) : $raw;
    }
}
