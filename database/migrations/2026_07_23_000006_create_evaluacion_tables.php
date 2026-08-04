<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('criterios_evaluacion')) {
            Schema::create('criterios_evaluacion', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
                $table->string('codigo', 30);
                $table->string('origen', 30);
                $table->string('nombre', 100);
                $table->decimal('porcentaje', 5, 2);
                $table->unsignedTinyInteger('orden');
                $table->timestamps();
                $table->unique(['programacion_academica_id', 'codigo']);
                $table->unique(['programacion_academica_id', 'origen']);
                $table->unique(['programacion_academica_id', 'orden']);
            });
        }

        if (! Schema::hasTable('examenes_finales')) {
            Schema::create('examenes_finales', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
                $table->string('titulo', 150);
                $table->text('descripcion')->nullable();
                $table->dateTime('inicio_at')->nullable();
                $table->dateTime('fin_at')->nullable();
                $table->decimal('puntaje_maximo', 6, 2);
                $table->decimal('nota_minima_aprobatoria', 6, 2);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique('programacion_academica_id');
            });
        }

        if (! Schema::hasTable('preguntas_examen')) {
            Schema::create('preguntas_examen', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('examen_final_id')->constrained('examenes_finales')->restrictOnDelete();
                $table->unsignedSmallInteger('orden');
                $table->string('tipo', 30);
                $table->text('enunciado');
                $table->decimal('puntaje', 6, 2);
                $table->timestamps();
                $table->unique(['examen_final_id', 'orden']);
            });
        }

        if (! Schema::hasTable('opciones_pregunta')) {
            Schema::create('opciones_pregunta', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pregunta_examen_id')->constrained('preguntas_examen')->restrictOnDelete();
                $table->unsignedSmallInteger('orden');
                $table->string('texto', 500);
                $table->boolean('es_correcta')->default(false);
                $table->timestamps();
                $table->unique(['pregunta_examen_id', 'orden']);
            });
        }

        if (! Schema::hasTable('intentos_examen')) {
            Schema::create('intentos_examen', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('examen_final_id')->constrained('examenes_finales')->restrictOnDelete();
                $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
                $table->dateTime('inicio_at');
                $table->dateTime('fin_at')->nullable();
                $table->string('estado', 30);
                $table->decimal('puntaje_obtenido', 6, 2)->nullable();
                $table->timestamps();
                $table->index(['matricula_id', 'estado']);
                $table->index(['examen_final_id', 'inicio_at']);
            });
        }

        if (! Schema::hasTable('respuestas_examen')) {
            Schema::create('respuestas_examen', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('intento_examen_id')->constrained('intentos_examen')->restrictOnDelete();
                $table->foreignId('pregunta_examen_id')->constrained('preguntas_examen')->restrictOnDelete();
                $table->foreignId('opcion_pregunta_id')->nullable()->constrained('opciones_pregunta')->restrictOnDelete();
                $table->text('respuesta_texto')->nullable();
                $table->boolean('es_correcta')->nullable();
                $table->decimal('puntaje_obtenido', 6, 2)->nullable();
                $table->timestamps();
                $table->unique(['intento_examen_id', 'pregunta_examen_id']);
            });
        }

        if (! Schema::hasTable('calificaciones')) {
            Schema::create('calificaciones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
                $table->decimal('promedio_tareas', 6, 2)->nullable();
                $table->decimal('nota_examen_final', 6, 2)->nullable();
                $table->decimal('nota_final', 6, 2);
                $table->string('estado', 30);
                $table->dateTime('calculado_at');
                $table->timestamps();
                $table->unique('matricula_id');
                $table->index(['estado', 'calculado_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['calificaciones', 'respuestas_examen', 'intentos_examen', 'opciones_pregunta', 'preguntas_examen', 'examenes_finales', 'criterios_evaluacion'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
