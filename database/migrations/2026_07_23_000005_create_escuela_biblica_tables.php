<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aulas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('capacidad')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['iglesia_id', 'codigo']);
            $table->index(['iglesia_id', 'activo']);
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['iglesia_id', 'codigo']);
            $table->index(['iglesia_id', 'activo']);
        });
        Schema::create('programaciones_academicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('aula_id')->nullable()->constrained('aulas')->restrictOnDelete();
            $table->string('periodo', 50);
            $table->string('grupo', 60);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('capacidad');
            $table->decimal('escala_maxima', 6, 2);
            $table->decimal('nota_minima_aprobatoria', 6, 2);
            $table->unsignedTinyInteger('maximo_intentos_examen')->default(1);
            $table->string('estado', 30);
            $table->timestamps();
            $table->unique(['curso_id', 'periodo', 'grupo']);
            $table->index(['curso_id', 'estado']);
            $table->index(['aula_id', 'fecha_inicio']);
        });
        Schema::create('programacion_estados_membresia_permitidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id');
            $table->foreignId('estado_membresia_id');
            $table->timestamps();
            $table->unique(['programacion_academica_id', 'estado_membresia_id']);
            $table->foreign('programacion_academica_id', 'fk_prog_estado_prog')
                ->references('id')
                ->on('programaciones_academicas')
                ->restrictOnDelete();
            $table->foreign('estado_membresia_id', 'fk_prog_estado_estado')
                ->references('id')
                ->on('estados_membresia')
                ->restrictOnDelete();
        });
        Schema::create('programacion_docentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnDelete();
            $table->timestamp('asignado_at')->useCurrent();
            $table->timestamps();
            $table->unique(['programacion_academica_id', 'miembro_id']);
            $table->index('miembro_id');
        });
        Schema::create('lecciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['curso_id', 'orden']);
            $table->index(['curso_id', 'activo']);
        });
        Schema::create('sesiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->dateTime('inicio_at');
            $table->dateTime('fin_at');
            $table->string('tema', 255)->nullable();
            $table->string('estado', 30);
            $table->timestamps();
            $table->unique(['programacion_academica_id', 'orden']);
            $table->index(['programacion_academica_id', 'inicio_at']);
        });
        Schema::create('sesion_lecciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones')->restrictOnDelete();
            $table->foreignId('leccion_id')->constrained('lecciones')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['sesion_id', 'leccion_id']);
        });
        Schema::create('matriculas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnDelete();
            $table->dateTime('fecha_matricula');
            $table->string('estado', 30);
            $table->timestamps();
            $table->unique(['programacion_academica_id', 'miembro_id']);
            $table->index(['miembro_id', 'estado']);
            $table->index(['programacion_academica_id', 'estado']);
        });
        Schema::create('asistencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones')->restrictOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
            $table->string('estado', 20);
            $table->string('observacion', 255)->nullable();
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['sesion_id', 'matricula_id']);
            $table->index('matricula_id');
        });
        Schema::create('tipos_material', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('materiales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
            $table->foreignId('tipo_material_id')->constrained('tipos_material')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('ruta_recurso', 2048);
            $table->dateTime('publicado_at')->nullable();
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['programacion_academica_id', 'publicado_at']);
        });
        Schema::create('tareas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('programacion_academica_id')->constrained('programaciones_academicas')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->dateTime('publicado_at');
            $table->dateTime('fecha_limite_at')->nullable();
            $table->decimal('puntaje_maximo', 6, 2);
            $table->foreignId('creado_por_usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->index(['programacion_academica_id', 'fecha_limite_at']);
        });
        Schema::create('entregas_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->restrictOnDelete();
            $table->foreignId('matricula_id')->constrained('matriculas')->restrictOnDelete();
            $table->text('contenido')->nullable();
            $table->string('ruta_archivo', 2048)->nullable();
            $table->dateTime('entregado_at');
            $table->decimal('nota', 6, 2)->nullable();
            $table->text('retroalimentacion')->nullable();
            $table->dateTime('calificado_at')->nullable();
            $table->foreignId('calificado_por_usuario_id')->nullable()->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tarea_id', 'matricula_id']);
            $table->index(['matricula_id', 'entregado_at']);
        });
    }

    public function down(): void
    {
        foreach (['entregas_tarea', 'tareas', 'materiales', 'tipos_material', 'asistencias', 'matriculas', 'sesion_lecciones', 'sesiones', 'lecciones', 'programacion_docentes', 'programacion_estados_membresia_permitidos', 'programaciones_academicas', 'cursos', 'aulas'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
