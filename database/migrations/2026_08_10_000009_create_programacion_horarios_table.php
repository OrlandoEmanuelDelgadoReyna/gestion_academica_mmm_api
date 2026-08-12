<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programacion_horarios')) {
            Schema::create('programacion_horarios', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programacion_academica_id')
                    ->constrained('programaciones_academicas')
                    ->restrictOnDelete();
                $table->unsignedTinyInteger('dia_semana');
                $table->time('hora_inicio');
                $table->time('hora_fin');
                $table->timestamps();

                $table->unique(
                    ['programacion_academica_id', 'dia_semana', 'hora_inicio', 'hora_fin'],
                    'uk_prog_horario_dia_rango'
                );
                $table->index(
                    ['programacion_academica_id', 'dia_semana', 'hora_inicio', 'hora_fin'],
                    'idx_prog_horario_consulta'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programacion_horarios');
    }
};
