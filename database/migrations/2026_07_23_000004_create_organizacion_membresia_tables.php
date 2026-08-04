<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las tablas organizacionales y del ciclo de membresía.
     */
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnUpdate()->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['iglesia_id', 'codigo']);
            $table->index(['iglesia_id', 'activo']);
        });

        Schema::create('sociedades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('iglesia_id')->constrained('iglesias')->restrictOnUpdate()->restrictOnDelete();
            $table->string('codigo', 60);
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['iglesia_id', 'codigo']);
            $table->index(['iglesia_id', 'activo']);
        });

        Schema::create('estados_membresia', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 120);
            $table->unsignedTinyInteger('orden')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('miembro_cargos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->restrictOnUpdate()->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['miembro_id', 'cargo_id', 'fecha_inicio']);
            $table->index(['miembro_id', 'activo']);
        });

        Schema::create('miembro_sociedades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('sociedad_id')->constrained('sociedades')->restrictOnUpdate()->restrictOnDelete();
            $table->date('fecha_ingreso');
            $table->date('fecha_salida')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['miembro_id', 'sociedad_id', 'fecha_ingreso']);
            $table->index(['miembro_id', 'activo']);
        });

        Schema::create('transiciones_estado_membresia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estado_origen_id')->constrained('estados_membresia')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('estado_destino_id')->constrained('estados_membresia')->restrictOnUpdate()->restrictOnDelete();
            $table->boolean('requiere_observacion')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['estado_origen_id', 'estado_destino_id']);
        });

        Schema::create('historial_membresia', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('miembro_id')->constrained('miembros')->restrictOnUpdate()->restrictOnDelete();
            $table->foreignId('estado_membresia_id')->constrained('estados_membresia')->restrictOnUpdate()->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por_usuario_id')->constrained('usuarios')->restrictOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->index(['miembro_id', 'fecha_inicio']);
            $table->index(['estado_membresia_id', 'fecha_inicio']);
        });
    }

    /**
     * Elimina las tablas organizacionales y del ciclo de membresía.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_membresia');
        Schema::dropIfExists('transiciones_estado_membresia');
        Schema::dropIfExists('miembro_sociedades');
        Schema::dropIfExists('miembro_cargos');
        Schema::dropIfExists('estados_membresia');
        Schema::dropIfExists('sociedades');
        Schema::dropIfExists('cargos');
    }
};
